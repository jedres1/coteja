<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\AccountingPeriod;
use App\Models\AccountingYearPeriod;
use App\Models\BillingInvoice;
use App\Models\CostCenter;
use App\Models\PurchaseInvoice;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\AccountingReportService;
use App\Traits\ExportsCsv;
use App\Traits\HasAccountingTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountingController extends Controller
{
    use HasAccountingTenant, ExportsCsv;

    public function __construct(
        private readonly AccountingPeriodService $periods,
        private readonly AccountingReportService $reports,
    ) {}

    // ── Catálogo de cuentas ─────────────────────────────────────────────────

    public function catalogo(Request $request)
    {
        $companyId = $this->requireTenant();

        $filter = $request->query('tipo', '');
        $search = trim((string) $request->query('search', ''));

        $query = AccountingAccount::forCompany($companyId)->orderBy('code');

        if ($filter !== '') {
            $query->where('type', $filter);
        }

        if ($search !== '') {
            $query->where(fn($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"));
        }

        $accounts = $query->get();

        $parents = AccountingAccount::forCompany($companyId)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'level'])
            ->map(fn($a) => ['id' => $a->id, 'code' => $a->code, 'name' => "{$a->code} - {$a->name}"])
            ->values();

        $countByType = AccountingAccount::forCompany($companyId)
            ->groupBy('type')
            ->selectRaw('type, COUNT(*) as total')
            ->pluck('total', 'type');
        $stats = [
            'activo'     => (int) $countByType->get('activo', 0),
            'pasivo'     => (int) $countByType->get('pasivo', 0),
            'patrimonio' => (int) $countByType->get('patrimonio', 0),
            'gasto'      => (int) $countByType->get('gasto', 0),
            'ingreso'    => (int) $countByType->get('ingreso', 0),
            'total'      => (int) $countByType->sum(),
        ];

        return Inertia::render('Admin/Accounting/Catalogo', compact('accounts', 'parents', 'stats', 'filter', 'search'));
    }

    public function store(Request $request)
    {
        $companyId = $this->requireTenant();

        $data = $request->validate([
            'code'      => ['required', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('accounting_accounts')->where('company_id', $companyId)],
            'name'      => 'required|string|max:200',
            'type'      => 'required|in:activo,pasivo,patrimonio,gasto,ingreso',
            'nature'    => 'required|in:deudora,acreedora',
            'parent_id' => ['nullable', \Illuminate\Validation\Rule::exists('accounting_accounts', 'id')
                ->where('company_id', $companyId)],
            'notes'     => 'nullable|string|max:1000',
        ]);

        $parent = $data['parent_id'] ? AccountingAccount::find($data['parent_id']) : null;
        $data['level']      = $parent ? $parent->level + 1 : 1;
        $data['company_id'] = $companyId;

        $account = AccountingAccount::create($data);

        return response()->json(['success' => true, 'message' => 'Cuenta creada.', 'account' => $account]);
    }

    public function update(Request $request, AccountingAccount $account)
    {
        $companyId = $this->requireTenant();
        abort_unless($account->company_id === $companyId, 403);

        $data = $request->validate([
            'code'      => ['required', 'string', 'max:20',
                \Illuminate\Validation\Rule::unique('accounting_accounts')->where('company_id', $companyId)->ignore($account->id)],
            'name'      => 'required|string|max:200',
            'type'      => 'required|in:activo,pasivo,patrimonio,gasto,ingreso',
            'nature'    => 'required|in:deudora,acreedora',
            'parent_id' => ['nullable', \Illuminate\Validation\Rule::exists('accounting_accounts', 'id')
                ->where('company_id', $companyId)],
            'is_active' => 'boolean',
            'notes'     => 'nullable|string|max:1000',
        ]);

        if (isset($data['parent_id']) && $data['parent_id'] == $account->id) {
            return response()->json(['success' => false, 'message' => 'Una cuenta no puede ser su propio padre.'], 422);
        }

        $parent = $data['parent_id'] ? AccountingAccount::find($data['parent_id']) : null;
        $data['level'] = $parent ? $parent->level + 1 : 1;

        $account->update($data);

        return response()->json(['success' => true, 'message' => 'Cuenta actualizada.', 'account' => $account->fresh()]);
    }

    public function destroy(AccountingAccount $account)
    {
        $companyId = $this->requireTenant();
        abort_unless($account->company_id === $companyId, 403);

        if ($account->children()->exists()) {
            return response()->json(['success' => false, 'message' => 'No se puede eliminar una cuenta que tiene subcuentas.'], 422);
        }

        $account->delete();

        return response()->json(['success' => true, 'message' => 'Cuenta eliminada.']);
    }

    // ── Paquetes Contables ──────────────────────────────────────────────────

    public function configuracion()
    {
        $companyId = $this->requireTenant();

        $packages = AccountingPackage::forCompany($companyId)->with([
            'debitAccount', 'creditAccount',
            'secondaryDebitAccount', 'secondaryCreditAccount', 'costCenter',
        ])->orderBy('code')->get();

        $accounts = AccountingAccount::forCompany($companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn($a) => ['id' => $a->id, 'label' => $a->code . ' — ' . $a->name, 'type' => $a->type])
            ->values();

        $costCenters = CostCenter::forCompany($companyId)
            ->orderBy('level')->orderBy('code')
            ->get(['id', 'code', 'name', 'description', 'is_active', 'parent_id', 'level']);

        $leafCostCenters = CostCenter::forCompany($companyId)
            ->leafCenters()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Admin/Accounting/Configuracion', compact('packages', 'accounts', 'costCenters', 'leafCostCenters'));
    }

    public function storePackage(Request $request)
    {
        $companyId = $this->requireTenant();

        $data = $request->validate([
            'code'                        => ['required', 'string', 'max:10',
                Rule::unique('accounting_packages', 'code')->where('company_id', $companyId)],
            'name'                        => 'required|string|max:100',
            'description'                 => 'nullable|string|max:500',
            'type'                        => 'required|in:automatico,manual',
            'debit_account_id'            => ['nullable', Rule::exists('accounting_accounts', 'id')->where('company_id', $companyId)],
            'credit_account_id'           => ['nullable', Rule::exists('accounting_accounts', 'id')->where('company_id', $companyId)],
            'secondary_debit_account_id'  => ['nullable', Rule::exists('accounting_accounts', 'id')->where('company_id', $companyId)],
            'secondary_credit_account_id' => ['nullable', Rule::exists('accounting_accounts', 'id')->where('company_id', $companyId)],
            'cost_center_id'              => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'is_active'                   => 'boolean',
        ]);

        if (!empty($data['cost_center_id']) && ! CostCenter::where('id', $data['cost_center_id'])->leafCenters()->exists()) {
            return back()->withErrors(['cost_center_id' => 'El centro de costo seleccionado tiene sub-centros y no puede asignarse a un paquete.']);
        }

        $data['code']       = strtoupper($data['code']);
        $data['company_id'] = $companyId;
        AccountingPackage::create($data);

        return back()->with('status', 'Paquete creado.');
    }

    public function updatePackage(Request $request, AccountingPackage $package)
    {
        $companyId = $this->requireTenant();
        abort_unless($package->company_id === $companyId, 403);

        $data = $request->validate([
            'name'                        => 'required|string|max:100',
            'description'                 => 'nullable|string|max:500',
            'type'                        => 'required|in:automatico,manual',
            'debit_account_id'            => ['nullable', Rule::exists('accounting_accounts', 'id')->where('company_id', $companyId)],
            'credit_account_id'           => ['nullable', Rule::exists('accounting_accounts', 'id')->where('company_id', $companyId)],
            'secondary_debit_account_id'  => ['nullable', Rule::exists('accounting_accounts', 'id')->where('company_id', $companyId)],
            'secondary_credit_account_id' => ['nullable', Rule::exists('accounting_accounts', 'id')->where('company_id', $companyId)],
            'cost_center_id'              => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'is_active'                   => 'boolean',
        ]);

        if (!empty($data['cost_center_id']) && ! CostCenter::where('id', $data['cost_center_id'])->leafCenters()->exists()) {
            return back()->withErrors(['cost_center_id' => 'El centro de costo seleccionado tiene sub-centros y no puede asignarse a un paquete.']);
        }

        $package->update($data);

        return back()->with('status', 'Paquete actualizado.');
    }

    public function destroyPackage(AccountingPackage $package)
    {
        $companyId = $this->requireTenant();
        abort_unless($package->company_id === $companyId, 403);

        if ($package->journalEntries()->exists()) {
            return back()->with('error', 'No se puede eliminar un paquete que tiene asientos contables registrados.');
        }

        $package->delete();

        return back()->with('status', 'Paquete eliminado.');
    }

    // ── Centros de costo ────────────────────────────────────────────────────

    public function storeCostCenter(Request $request)
    {
        $companyId = $this->requireTenant();

        $data = $request->validate([
            'parent_id'   => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'code'        => ['required', 'string', 'max:20',
                Rule::unique('cost_centers', 'code')->where('company_id', $companyId)],
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);

        $parent = $data['parent_id'] ? CostCenter::find($data['parent_id']) : null;
        $data['level']      = $parent ? $parent->level + 1 : 1;
        $data['code']       = strtoupper($data['code']);
        $data['company_id'] = $companyId;
        CostCenter::create($data);

        return back()->with('status', 'Centro de costo creado.');
    }

    public function updateCostCenter(Request $request, CostCenter $costCenter)
    {
        $companyId = $this->requireTenant();
        abort_unless($costCenter->company_id === $companyId, 403);

        $data = $request->validate([
            'parent_id'   => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'code'        => ['required', 'string', 'max:20',
                Rule::unique('cost_centers', 'code')->where('company_id', $companyId)->ignore($costCenter->id)],
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);

        if ($data['parent_id'] && (int) $data['parent_id'] === $costCenter->id) {
            return back()->withErrors(['parent_id' => 'Un centro de costo no puede ser su propio padre.']);
        }

        $parent        = $data['parent_id'] ? CostCenter::find($data['parent_id']) : null;
        $data['level'] = $parent ? $parent->level + 1 : 1;
        $data['code']  = strtoupper($data['code']);
        $costCenter->update($data);

        return back()->with('status', 'Centro de costo actualizado.');
    }

    public function destroyCostCenter(CostCenter $costCenter)
    {
        $companyId = $this->requireTenant();
        abort_unless($costCenter->company_id === $companyId, 403);

        if ($costCenter->children()->exists()) {
            return back()->with('error', 'No se puede eliminar un centro que tiene sub-centros. Elimine primero los hijos.');
        }

        if ($costCenter->accountingPackages()->exists() || $costCenter->journalEntryLines()->exists()) {
            return back()->with('error', 'No se puede eliminar un centro de costo en uso. Puede desactivarlo.');
        }

        $costCenter->delete();

        return back()->with('status', 'Centro de costo eliminado.');
    }

    // ── Períodos Contables ──────────────────────────────────────────────────

    public function periodos(Request $request)
    {
        $companyId = $this->requireTenant();
        $year      = (int) $request->query('year', now()->year);

        $yearPeriod = AccountingYearPeriod::forCompany($companyId)->where('year', $year)->first();
        $periods    = AccountingPeriod::forCompany($companyId)->where('year', $year)->orderBy('month')->get();

        $availableYears = AccountingYearPeriod::forCompany($companyId)->distinct()->orderByDesc('year')->pluck('year')->toArray();

        foreach (AccountingPeriod::forCompany($companyId)->distinct()->pluck('year')->toArray() as $y) {
            if (!in_array($y, $availableYears)) $availableYears[] = $y;
        }

        if (!in_array(now()->year, $availableYears)) $availableYears[] = now()->year;

        rsort($availableYears);

        return Inertia::render('Admin/Accounting/Periodos', compact('yearPeriod', 'periods', 'year', 'availableYears'));
    }

    public function generarPeriodos(Request $request)
    {
        $companyId = $this->requireTenant();
        $data      = $request->validate(['year' => 'required|integer|min:2020|max:2099']);
        $created   = $this->periods->createYearPeriods((int) $data['year'], $companyId);

        $msg = $created > 0
            ? "Ejercicio {$data['year']} creado con {$created} mes(es)."
            : "El ejercicio {$data['year']} y sus 12 meses ya existen.";

        return back()->with('status', $msg);
    }

    public function abrirAnioPeriodo(Request $request, AccountingYearPeriod $yearPeriod)
    {
        $companyId = $this->requireTenant();
        abort_unless((int) $yearPeriod->company_id === $companyId, 403);

        if ($yearPeriod->isOpen()) {
            return back()->with('error', 'El año contable ya está abierto.');
        }

        $this->periods->openYear($yearPeriod);

        return back()->with('status', "Ejercicio {$yearPeriod->year} abierto.");
    }

    public function cerrarAnioPeriodo(Request $request, AccountingYearPeriod $yearPeriod)
    {
        $companyId = $this->requireTenant();
        abort_unless((int) $yearPeriod->company_id === $companyId, 403);

        if (!$yearPeriod->isOpen()) {
            return back()->with('error', 'El año contable ya está cerrado.');
        }

        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $this->periods->closeYear($yearPeriod, $request->user()->id, $data['notes'] ?? null);

        return back()->with('status', "Ejercicio {$yearPeriod->year} cerrado junto con sus meses abiertos.");
    }

    public function abrirPeriodo(Request $request, AccountingPeriod $period)
    {
        $companyId  = $this->requireTenant();
        abort_unless((int) $period->company_id === $companyId, 403);

        $yearPeriod = AccountingYearPeriod::forCompany($companyId)->where('year', $period->year)->first();
        if (!$yearPeriod || !$yearPeriod->isOpen()) {
            return back()->with('error', 'Abra primero el año contable antes de abrir meses.');
        }

        if ($period->isOpen()) {
            return back()->with('error', 'El período ya está abierto.');
        }

        $this->periods->openPeriod($period);

        return back()->with('status', "Período {$period->full_name} abierto.");
    }

    public function cerrarPeriodo(Request $request, AccountingPeriod $period)
    {
        $companyId = $this->requireTenant();
        abort_unless((int) $period->company_id === $companyId, 403);

        if (!$period->isOpen()) {
            return back()->with('error', 'El período ya está cerrado.');
        }

        $data = $request->validate(['notes' => 'nullable|string|max:500']);
        $this->periods->closePeriod($period, $request->user()->id, $data['notes'] ?? null);

        return back()->with('status', "Período {$period->full_name} cerrado.");
    }

    // ── Saldos por período ──────────────────────────────────────────────────

    public function saldos(Request $request)
    {
        $companyId = $this->requireTenant();
        $year      = (int) $request->query('year',  now()->year);
        $month     = (int) $request->query('month', now()->month);
        $accountId = $request->query('account_id');
        $acumulado = $request->boolean('acumulado', false);

        ['balances' => $balances, 'summaryByType' => $summaryByType] =
            $this->reports->saldos($companyId, $year, $month, $accountId, $acumulado);

        $period     = AccountingPeriod::forCompany($companyId)->where('year', $year)->where('month', $month)->first();
        $accounts   = AccountingAccount::forCompany($companyId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $allPeriods = AccountingPeriod::forCompany($companyId)->where('year', $year)->orderBy('month')->get();

        return Inertia::render('Admin/Accounting/Saldos', compact(
            'balances', 'summaryByType', 'accounts',
            'period', 'year', 'month', 'accountId', 'acumulado', 'allPeriods'
        ));
    }

    // ── Balanza de Comprobación ─────────────────────────────────────────────

    public function balanzaComprobacion(Request $request)
    {
        $companyId         = $this->requireTenant();
        $year              = (int) $request->query('year',  now()->year);
        $month             = (int) $request->query('month', now()->month);
        $acumulado         = $request->boolean('acumulado', false);
        $tipo              = $request->query('tipo', '');
        $soloConMovimiento = $request->boolean('solo_movimiento', false);

        [
            'rows'               => $rows,
            'totalSumaDebe'      => $totalSumaDebe,
            'totalSumaHaber'     => $totalSumaHaber,
            'totalSaldoDeudor'   => $totalSaldoDeudor,
            'totalSaldoAcreedor' => $totalSaldoAcreedor,
        ] = $this->reports->balanza($companyId, $year, $month, $acumulado, $tipo, $soloConMovimiento);

        $period         = AccountingPeriod::forCompany($companyId)->where('year', $year)->where('month', $month)->first();
        $availableYears = $this->reports->availableYears($companyId);

        return Inertia::render('Admin/Accounting/BalanzaComprobacion', compact(
            'rows', 'period', 'year', 'month', 'acumulado', 'tipo', 'soloConMovimiento',
            'totalSumaDebe', 'totalSumaHaber', 'totalSaldoDeudor', 'totalSaldoAcreedor',
            'availableYears'
        ));
    }

    // ── Libro Mayor ─────────────────────────────────────────────────────────

    public function libroMayor(Request $request)
    {
        $companyId = $this->requireTenant();
        $year      = (int) $request->query('year',  now()->year);
        $month     = (int) $request->query('month', now()->month);
        $accountId = $request->query('account_id');
        $acumulado = $request->boolean('acumulado', false);

        [
            'ledger'      => $ledger,
            'allAccounts' => $allAccounts,
            'startDate'   => $startDate,
            'endDate'     => $endDate,
        ] = $this->reports->libroMayor($companyId, $year, $month, $accountId, $acumulado);

        $period         = AccountingPeriod::forCompany($companyId)->where('year', $year)->where('month', $month)->first();
        $availableYears = $this->reports->availableYears($companyId);

        return Inertia::render('Admin/Accounting/LibroMayor', compact(
            'ledger', 'allAccounts', 'period',
            'year', 'month', 'acumulado', 'accountId',
            'startDate', 'endDate', 'availableYears'
        ));
    }

    // ── Balance General ─────────────────────────────────────────────────────

    public function balanceGeneral(Request $request)
    {
        $companyId = $this->requireTenant();
        $year      = (int) $request->query('year',  now()->year);
        $month     = (int) $request->query('month', now()->month);

        ['rows' => $rows, 'bySection' => $bySection, 'sections' => $sections] =
            $this->reports->balanceGeneral($companyId, $year, $month);

        $period         = AccountingPeriod::forCompany($companyId)->where('year', $year)->where('month', $month)->first();
        $availableYears = $this->reports->availableYears($companyId);

        return Inertia::render('Admin/Accounting/BalanceGeneral', compact(
            'rows', 'bySection', 'sections', 'period', 'year', 'month', 'availableYears'
        ));
    }

    // ── Estado de Resultados ────────────────────────────────────────────────

    public function estadoResultados(Request $request)
    {
        $companyId = $this->requireTenant();
        $year      = (int) $request->query('year',  now()->year);
        $month     = (int) $request->query('month', now()->month);
        $acumulado = $request->boolean('acumulado', false);

        [
            'rows'           => $rows,
            'bySection'      => $bySection,
            'sections'       => $sections,
            'totalIngresos'  => $totalIngresos,
            'totalGastos'    => $totalGastos,
            'utilidad'       => $utilidad,
        ] = $this->reports->estadoResultados($companyId, $year, $month, $acumulado);

        $period         = AccountingPeriod::forCompany($companyId)->where('year', $year)->where('month', $month)->first();
        $availableYears = $this->reports->availableYears($companyId);

        return Inertia::render('Admin/Accounting/EstadoResultados', compact(
            'rows', 'bySection', 'sections', 'period', 'year', 'month',
            'acumulado', 'totalIngresos', 'totalGastos', 'utilidad', 'availableYears'
        ));
    }

    // ── Reportes Fiscales (F-07 / F-14) ────────────────────────────────────

    public function reportesFiscales(Request $request)
    {
        $companyId = $this->tenantId();
        if ($companyId === null) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Seleccione una empresa activa para ver los reportes fiscales.');
        }
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->endOfMonth()->toDateString();

        $ventas = BillingInvoice::where('company_id', $companyId)
            ->whereDate('issued_at', '>=', $from)
            ->whereDate('issued_at', '<=', $to)
            ->whereNotNull('reception_stamp')
            ->where('status', '!=', 'ANULADO')
            ->orderBy('issued_at')->orderBy('id')
            ->get()
            ->values()
            ->map(fn ($inv, $i) => array_merge(['correlativo' => $i + 1], $this->formatVenta($inv)));

        $compras = PurchaseInvoice::with('supplier')
            ->where('company_id', $companyId)
            ->whereDate('purchase_date', '>=', $from)
            ->whereDate('purchase_date', '<=', $to)
            ->where('status', 'approved')
            ->orderBy('purchase_date')->orderBy('id')
            ->get()
            ->values()
            ->map(fn ($inv, $i) => array_merge(['correlativo' => $i + 1], $this->formatCompra($inv)));

        return Inertia::render('Admin/Accounting/ReportesFiscales', compact('ventas', 'compras', 'from', 'to'));
    }

    public function exportLibroVentas(Request $request)
    {
        $companyId = $this->tenantId();
        if ($companyId === null) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Seleccione una empresa activa para exportar el libro de ventas.');
        }
        $filters = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
        $from = $filters['from'];
        $to = $filters['to'];

        $ventas = BillingInvoice::where('company_id', $companyId)
            ->whereDate('issued_at', '>=', $from)
            ->whereDate('issued_at', '<=', $to)
            ->whereNotNull('reception_stamp')
            ->where('status', '!=', 'ANULADO')
            ->orderBy('issued_at')->orderBy('id')
            ->get();

        $headers = ['N°', 'Fecha', 'Tipo DTE', 'N° Control', 'Nombre Receptor', 'NRC Receptor', 'NIT Receptor', 'Monto Exento', 'Monto No Sujeto', 'Monto Gravado', 'Débito Fiscal', 'Total'];

        $rows = $ventas->values()->map(function ($inv, $i) {
            $f = $this->formatVenta($inv);
            return [
                $i + 1,
                $f['fecha'], $f['tipo_dte'], $f['numero_control'],
                $f['receptor_nombre'], $f['receptor_nrc'], $f['receptor_nit'],
                number_format($f['monto_exento'],  2, '.', ''),
                number_format($f['monto_no_suj'],  2, '.', ''),
                number_format($f['monto_gravado'], 2, '.', ''),
                number_format($f['debito_fiscal'], 2, '.', ''),
                number_format($f['total'],         2, '.', ''),
            ];
        });

        return $this->streamCsv("F07_LibroVentas_{$from}_{$to}.csv", $headers, $rows);
    }

    public function exportLibroCompras(Request $request)
    {
        $companyId = $this->tenantId();
        if ($companyId === null) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Seleccione una empresa activa para exportar el libro de compras.');
        }
        $filters = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
        $from = $filters['from'];
        $to = $filters['to'];

        $compras = PurchaseInvoice::with('supplier')
            ->where('company_id', $companyId)
            ->whereDate('purchase_date', '>=', $from)
            ->whereDate('purchase_date', '<=', $to)
            ->where('status', 'approved')
            ->orderBy('purchase_date')->orderBy('id')
            ->get();

        $headers = ['N°', 'Fecha', 'Tipo DTE', 'N° Documento', 'Nombre Proveedor', 'NRC Proveedor', 'NIT Proveedor', 'Monto Exento', 'Monto No Sujeto', 'Monto Gravado', 'Crédito Fiscal', 'Total'];

        $rows = $compras->values()->map(function ($inv, $i) {
            $f = $this->formatCompra($inv);
            return [
                $i + 1,
                $f['fecha'], $f['tipo_dte'], $f['numero_control'],
                $f['proveedor_nombre'], $f['proveedor_nrc'], $f['proveedor_nit'],
                number_format($f['monto_exento'],  2, '.', ''),
                number_format($f['monto_no_suj'],  2, '.', ''),
                number_format($f['monto_gravado'], 2, '.', ''),
                number_format($f['credito_fiscal'],2, '.', ''),
                number_format($f['total'],         2, '.', ''),
            ];
        });

        return $this->streamCsv("F14_LibroCompras_{$from}_{$to}.csv", $headers, $rows);
    }

    private function formatVenta(BillingInvoice $inv): array
    {
        $dte     = is_array($inv->json_dte) ? $inv->json_dte : [];
        $receptor = $dte['receptor'] ?? [];
        $resumen  = $dte['resumen']  ?? [];

        $exento  = (float) ($resumen['totalExenta'] ?? 0);
        $noSuj   = (float) ($resumen['totalNoSuj']  ?? 0);
        $gravado = (float) ($resumen['subTotalVentas'] ?? (($exento === 0.0 && $noSuj === 0.0) ? (float) $inv->subtotal : 0.0));
        $iva     = (float) ($resumen['totalIva']    ?? $inv->iva);
        $total   = (float) ($resumen['totalPagar']  ?? $inv->total);

        return [
            'fecha'           => $inv->issued_at?->format('d/m/Y'),
            'tipo_dte'        => $inv->document_type,
            'numero_control'  => $inv->number_control,
            'receptor_nombre' => $receptor['nombre'] ?? $inv->customer_name,
            'receptor_nrc'    => $receptor['nrc'] ?? null,
            'receptor_nit'    => $receptor['numDocumento'] ?? null,
            'monto_exento'    => $exento,
            'monto_no_suj'    => $noSuj,
            'monto_gravado'   => $gravado,
            'debito_fiscal'   => $iva,
            'total'           => $total,
        ];
    }

    private function formatCompra(PurchaseInvoice $inv): array
    {
        $supplier = $inv->supplier;
        $subtotal = (float) $inv->subtotal;
        $iva      = (float) $inv->iva;
        $total    = (float) $inv->total;

        return [
            'fecha'            => $inv->purchase_date?->format('d/m/Y'),
            'tipo_dte'         => $inv->document_type,
            'numero_control'   => $inv->invoice_number,
            'proveedor_nombre' => $supplier?->name ?? '',
            'proveedor_nrc'    => $supplier?->nrc ?? null,
            'proveedor_nit'    => $supplier?->document_number ?? null,
            'monto_exento'     => $iva == 0 ? $subtotal : 0.0,
            'monto_no_suj'     => 0.0,
            'monto_gravado'    => $iva > 0  ? $subtotal : 0.0,
            'credito_fiscal'   => $iva,
            'total'            => $total,
        ];
    }
}
