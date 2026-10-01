<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\AccountingPeriod;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    public function __construct(private readonly AccountingPeriodService $periods) {}

    // ── Catálogo de cuentas ─────────────────────────────────────────────────

    public function catalogo(Request $request)
    {
        $filter = $request->query('tipo', '');
        $search = trim((string) $request->query('search', ''));

        $query = AccountingAccount::query()->orderBy('code');

        if ($filter !== '') {
            $query->where('type', $filter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $accounts = $query->get();

        $parents = AccountingAccount::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'level'])
            ->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => "{$a->code} - {$a->name}"])
            ->values();

        $stats = [
            'activo'     => AccountingAccount::where('type', 'activo')->count(),
            'pasivo'     => AccountingAccount::where('type', 'pasivo')->count(),
            'patrimonio' => AccountingAccount::where('type', 'patrimonio')->count(),
            'gasto'      => AccountingAccount::where('type', 'gasto')->count(),
            'ingreso'    => AccountingAccount::where('type', 'ingreso')->count(),
            'total'      => AccountingAccount::count(),
        ];

        return view('admin.accounting.catalogo', compact('accounts', 'parents', 'stats', 'filter', 'search'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'      => 'required|string|max:20|unique:accounting_accounts,code',
            'name'      => 'required|string|max:200',
            'type'      => 'required|in:activo,pasivo,patrimonio,gasto,ingreso',
            'nature'    => 'required|in:deudora,acreedora',
            'parent_id' => 'nullable|exists:accounting_accounts,id',
            'notes'     => 'nullable|string|max:1000',
        ]);

        $parent = $data['parent_id'] ? AccountingAccount::find($data['parent_id']) : null;
        $data['level'] = $parent ? $parent->level + 1 : 1;

        $account = AccountingAccount::create($data);

        return response()->json(['success' => true, 'message' => 'Cuenta creada.', 'account' => $account]);
    }

    public function update(Request $request, AccountingAccount $account)
    {
        $data = $request->validate([
            'code'      => 'required|string|max:20|unique:accounting_accounts,code,'.$account->id,
            'name'      => 'required|string|max:200',
            'type'      => 'required|in:activo,pasivo,patrimonio,gasto,ingreso',
            'nature'    => 'required|in:deudora,acreedora',
            'parent_id' => 'nullable|exists:accounting_accounts,id',
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
        if ($account->children()->exists()) {
            return response()->json(['success' => false, 'message' => 'No se puede eliminar una cuenta que tiene subcuentas.'], 422);
        }

        $account->delete();

        return response()->json(['success' => true, 'message' => 'Cuenta eliminada.']);
    }

    // ── Paquetes Contables ──────────────────────────────────────────────────

    public function configuracion()
    {
        $packages = AccountingPackage::with([
            'debitAccount', 'creditAccount',
            'secondaryDebitAccount', 'secondaryCreditAccount',
        ])->orderBy('code')->get();

        $accounts = AccountingAccount::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn ($a) => ['id' => $a->id, 'label' => $a->code . ' — ' . $a->name, 'type' => $a->type])
            ->values();

        return view('admin.accounting.configuracion', compact('packages', 'accounts'));
    }

    public function storePackage(Request $request)
    {
        $data = $request->validate([
            'code'                        => 'required|string|max:10|unique:accounting_packages,code',
            'name'                        => 'required|string|max:100',
            'description'                 => 'nullable|string|max:500',
            'type'                        => 'required|in:automatico,manual',
            'debit_account_id'            => 'nullable|exists:accounting_accounts,id',
            'credit_account_id'           => 'nullable|exists:accounting_accounts,id',
            'secondary_debit_account_id'  => 'nullable|exists:accounting_accounts,id',
            'secondary_credit_account_id' => 'nullable|exists:accounting_accounts,id',
            'is_active'                   => 'boolean',
        ]);

        $data['code'] = strtoupper($data['code']);

        $package = AccountingPackage::create($data);

        return response()->json(['success' => true, 'message' => 'Paquete creado.', 'package' => $package]);
    }

    public function updatePackage(Request $request, AccountingPackage $package)
    {
        $data = $request->validate([
            'name'                        => 'required|string|max:100',
            'description'                 => 'nullable|string|max:500',
            'type'                        => 'required|in:automatico,manual',
            'debit_account_id'            => 'nullable|exists:accounting_accounts,id',
            'credit_account_id'           => 'nullable|exists:accounting_accounts,id',
            'secondary_debit_account_id'  => 'nullable|exists:accounting_accounts,id',
            'secondary_credit_account_id' => 'nullable|exists:accounting_accounts,id',
            'is_active'                   => 'boolean',
        ]);

        $package->update($data);

        return response()->json(['success' => true, 'message' => 'Paquete actualizado.', 'package' => $package->fresh()]);
    }

    public function destroyPackage(AccountingPackage $package)
    {
        if ($package->journalEntries()->exists()) {
            return response()->json(['success' => false, 'message' => 'No se puede eliminar un paquete que tiene asientos contables registrados.'], 422);
        }

        $package->delete();

        return response()->json(['success' => true, 'message' => 'Paquete eliminado.']);
    }

    // ── Períodos Contables ──────────────────────────────────────────────────

    public function periodos(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        $periods = AccountingPeriod::where('year', $year)
            ->orderBy('month')
            ->get();

        $availableYears = AccountingPeriod::distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->toArray();

        if (! in_array(now()->year, $availableYears)) {
            $availableYears[] = now()->year;
            sort($availableYears);
            $availableYears = array_reverse($availableYears);
        }

        return view('admin.accounting.periodos', compact('periods', 'year', 'availableYears'));
    }

    public function generarPeriodos(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $created = $this->periods->createYearPeriods((int) $data['year']);

        return response()->json([
            'success' => true,
            'message' => $created > 0
                ? "{$created} período(s) creado(s) para el año {$data['year']}."
                : "Todos los períodos del año {$data['year']} ya existen.",
        ]);
    }

    public function abrirPeriodo(Request $request, AccountingPeriod $period)
    {
        if ($period->isOpen()) {
            return response()->json(['success' => false, 'message' => 'El período ya está abierto.'], 422);
        }

        $this->periods->openPeriod($period);

        return response()->json(['success' => true, 'message' => "Período {$period->full_name} abierto."]);
    }

    public function cerrarPeriodo(Request $request, AccountingPeriod $period)
    {
        if (! $period->isOpen()) {
            return response()->json(['success' => false, 'message' => 'El período ya está cerrado.'], 422);
        }

        $data = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $this->periods->closePeriod($period, $request->user()->id, $data['notes'] ?? null);

        return response()->json(['success' => true, 'message' => "Período {$period->full_name} cerrado."]);
    }

    // ── Saldos por período ──────────────────────────────────────────────────

    public function saldos(Request $request)
    {
        $year      = (int) $request->query('year', now()->year);
        $month     = (int) $request->query('month', now()->month);
        $accountId = $request->query('account_id');
        $acumulado = $request->boolean('acumulado', false);

        $period = AccountingPeriod::where('year', $year)->where('month', $month)->first();

        // Consulta de saldos sobre partidas aprobadas
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounting_accounts as aa', 'aa.id', '=', 'jel.account_id')
            ->where('je.status', 'aprobado')
            ->when($accountId, fn ($q) => $q->where('jel.account_id', $accountId))
            ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature')
            ->select([
                'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature',
                DB::raw('SUM(jel.debit) as total_debit'),
                DB::raw('SUM(jel.credit) as total_credit'),
            ])
            ->orderBy('aa.code');

        if ($acumulado) {
            // Saldo acumulado: desde 01-01-{year} hasta fin del mes seleccionado
            $query->whereYear('je.entry_date', $year)
                  ->whereRaw('MONTH(je.entry_date) <= ?', [$month]);
        } else {
            // Solo movimientos del período seleccionado
            $query->whereYear('je.entry_date', $year)
                  ->whereMonth('je.entry_date', $month);
        }

        $balances = $query->get()->map(function ($row) {
            $debe  = (float) $row->total_debit;
            $haber = (float) $row->total_credit;
            // Saldo natural: deudora = debe - haber, acreedora = haber - debe
            $saldo = $row->nature === 'deudora' ? $debe - $haber : $haber - $debe;
            return (object) [
                'id'          => $row->id,
                'code'        => $row->code,
                'name'        => $row->name,
                'type'        => $row->type,
                'nature'      => $row->nature,
                'total_debit' => $debe,
                'total_credit'=> $haber,
                'saldo'       => $saldo,
            ];
        });

        $summaryByType = $balances->groupBy('type')->map(fn ($g) => [
            'total_debit'  => $g->sum('total_debit'),
            'total_credit' => $g->sum('total_credit'),
            'saldo'        => $g->sum('saldo'),
        ]);

        $accounts = AccountingAccount::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        $allPeriods = AccountingPeriod::where('year', $year)->orderBy('month')->get();

        return view('admin.accounting.saldos', compact(
            'balances', 'summaryByType', 'accounts',
            'period', 'year', 'month', 'accountId', 'acumulado', 'allPeriods'
        ));
    }

    // ── Balanza de Comprobación ─────────────────────────────────────────────

    public function balanzaComprobacion(Request $request)
    {
        $year      = (int) $request->query('year',  now()->year);
        $month     = (int) $request->query('month', now()->month);
        $acumulado = $request->boolean('acumulado', false);
        $tipo      = $request->query('tipo', '');

        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounting_accounts as aa', 'aa.id', '=', 'jel.account_id')
            ->where('je.status', 'aprobado')
            ->when($tipo !== '', fn ($q) => $q->where('aa.type', $tipo))
            ->when($acumulado,
                fn ($q) => $q->whereYear('je.entry_date', $year)
                              ->whereRaw('MONTH(je.entry_date) <= ?', [$month]),
                fn ($q) => $q->whereYear('je.entry_date', $year)
                              ->whereMonth('je.entry_date', $month)
            )
            ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature')
            ->select([
                'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature',
                DB::raw('SUM(jel.debit)  as suma_debe'),
                DB::raw('SUM(jel.credit) as suma_haber'),
            ])
            ->orderBy('aa.code')
            ->get()
            ->map(function ($r) {
                $debe  = (float) $r->suma_debe;
                $haber = (float) $r->suma_haber;
                $neto  = $debe - $haber;
                return (object) [
                    'id'           => $r->id,
                    'code'         => $r->code,
                    'name'         => $r->name,
                    'type'         => $r->type,
                    'nature'       => $r->nature,
                    'suma_debe'    => $debe,
                    'suma_haber'   => $haber,
                    'saldo_deudor' => $neto > 0 ? $neto : 0.0,
                    'saldo_acreedor' => $neto < 0 ? abs($neto) : 0.0,
                ];
            });

        $totalSumaDebe    = $rows->sum('suma_debe');
        $totalSumaHaber   = $rows->sum('suma_haber');
        $totalSaldoDeudor = $rows->sum('saldo_deudor');
        $totalSaldoAcreedor = $rows->sum('saldo_acreedor');

        $period = AccountingPeriod::where('year', $year)->where('month', $month)->first();

        $availableYears = AccountingPeriod::distinct()->orderByDesc('year')->pluck('year')->toArray();
        if (!in_array(now()->year, $availableYears)) {
            $availableYears[] = now()->year;
            rsort($availableYears);
        }

        return view('admin.accounting.balanza-comprobacion', compact(
            'rows', 'period', 'year', 'month', 'acumulado', 'tipo',
            'totalSumaDebe', 'totalSumaHaber', 'totalSaldoDeudor', 'totalSaldoAcreedor',
            'availableYears'
        ));
    }

    // ── Balance General ─────────────────────────────────────────────────────

    public function balanceGeneral(Request $request)
    {
        $year  = (int) $request->query('year',  now()->year);
        $month = (int) $request->query('month', now()->month);

        // Secciones de nivel 2 para agrupar cuentas
        $sections = AccountingAccount::whereIn('type', ['activo', 'pasivo', 'patrimonio'])
            ->where('level', 2)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        // Balances acumulados desde el 01-01 hasta fin del mes seleccionado
        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounting_accounts as aa', 'aa.id', '=', 'jel.account_id')
            ->join('accounting_accounts as par', 'par.id', '=', 'aa.parent_id')
            ->where('je.status', 'aprobado')
            ->whereIn('aa.type', ['activo', 'pasivo', 'patrimonio'])
            ->where('aa.level', 3)
            ->whereYear('je.entry_date', $year)
            ->whereRaw('MONTH(je.entry_date) <= ?', [$month])
            ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature', 'aa.parent_id', 'par.code', 'par.name')
            ->select([
                'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature', 'aa.parent_id',
                'par.code as section_code', 'par.name as section_name',
                DB::raw('SUM(jel.debit)  as total_debit'),
                DB::raw('SUM(jel.credit) as total_credit'),
            ])
            ->orderBy('aa.code')
            ->get()
            ->map(function ($r) {
                $debe  = (float) $r->total_debit;
                $haber = (float) $r->total_credit;
                $saldo = $r->nature === 'deudora' ? $debe - $haber : $haber - $debe;
                return (object) array_merge((array) $r, [
                    'total_debit'  => $debe,
                    'total_credit' => $haber,
                    'saldo'        => $saldo,
                ]);
            });

        // Agrupar por sección (nivel 2) y luego por tipo
        $bySection = $rows->groupBy('section_code');

        $period = AccountingPeriod::where('year', $year)->where('month', $month)->first();

        $availableYears = AccountingPeriod::distinct()->orderByDesc('year')->pluck('year')->toArray();
        if (!in_array(now()->year, $availableYears)) {
            $availableYears[] = now()->year;
            rsort($availableYears);
        }

        return view('admin.accounting.balance-general', compact(
            'rows', 'bySection', 'sections', 'period', 'year', 'month', 'availableYears'
        ));
    }

    // ── Estado de Resultados ────────────────────────────────────────────────

    public function estadoResultados(Request $request)
    {
        $year      = (int) $request->query('year',  now()->year);
        $month     = (int) $request->query('month', now()->month);
        $acumulado = $request->boolean('acumulado', false);

        // Secciones de nivel 2 para ingresos y gastos
        $sections = AccountingAccount::whereIn('type', ['ingreso', 'gasto'])
            ->where('level', 2)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounting_accounts as aa', 'aa.id', '=', 'jel.account_id')
            ->join('accounting_accounts as par', 'par.id', '=', 'aa.parent_id')
            ->where('je.status', 'aprobado')
            ->whereIn('aa.type', ['ingreso', 'gasto'])
            ->where('aa.level', 3)
            ->whereYear('je.entry_date', $year)
            ->when($acumulado,
                fn ($q) => $q->whereRaw('MONTH(je.entry_date) <= ?', [$month]),
                fn ($q) => $q->whereMonth('je.entry_date', $month)
            )
            ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature', 'aa.parent_id', 'par.code', 'par.name')
            ->select([
                'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature', 'aa.parent_id',
                'par.code as section_code', 'par.name as section_name',
                DB::raw('SUM(jel.debit)  as total_debit'),
                DB::raw('SUM(jel.credit) as total_credit'),
            ])
            ->orderBy('aa.code')
            ->get()
            ->map(function ($r) {
                $debe  = (float) $r->total_debit;
                $haber = (float) $r->total_credit;
                $saldo = $r->nature === 'deudora' ? $debe - $haber : $haber - $debe;
                return (object) array_merge((array) $r, [
                    'total_debit'  => $debe,
                    'total_credit' => $haber,
                    'saldo'        => $saldo,
                ]);
            });

        $bySection    = $rows->groupBy('section_code');
        $totalIngresos = $rows->where('type', 'ingreso')->sum('saldo');
        $totalGastos   = $rows->where('type', 'gasto')->sum('saldo');
        $utilidad      = $totalIngresos - $totalGastos;

        $period = AccountingPeriod::where('year', $year)->where('month', $month)->first();

        $availableYears = AccountingPeriod::distinct()->orderByDesc('year')->pluck('year')->toArray();
        if (!in_array(now()->year, $availableYears)) {
            $availableYears[] = now()->year;
            rsort($availableYears);
        }

        return view('admin.accounting.estado-resultados', compact(
            'rows', 'bySection', 'sections', 'period', 'year', 'month',
            'acumulado', 'totalIngresos', 'totalGastos', 'utilidad', 'availableYears'
        ));
    }
}
