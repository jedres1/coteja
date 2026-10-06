<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AccountingPeriodException;
use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    public function __construct(private readonly AccountingPeriodService $periods) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');
        $from   = $request->query('from', now()->startOfMonth()->toDateString());
        $to     = $request->query('to', now()->toDateString());

        $query = JournalEntry::with(['lines', 'accountingPackage'])
            ->when($from,   fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to,     fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('entry_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reference',   'like', "%{$search}%")
                  ->orWhere('source_document', 'like', "%{$search}%");
            }))
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc');

        $entries = $query->paginate(20)->withQueryString();

        $stats = [
            'borrador' => JournalEntry::where('status', 'borrador')->count(),
            'aprobado' => JournalEntry::where('status', 'aprobado')->count(),
            'anulado'  => JournalEntry::where('status', 'anulado')->count(),
            'total'    => JournalEntry::count(),
        ];

        return view('admin.accounting.diario', compact('entries', 'stats', 'search', 'status', 'from', 'to'));
    }

    public function create()
    {
        $accounts = AccountingAccount::where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'nature']);

        $cgPackage = AccountingPackage::where('code', 'CG')->first();
        $costCenters = CostCenter::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        return view('admin.accounting.diario-nuevo', [
            'accounts'  => $accounts,
            'costCenters' => $costCenters,
            'entry'     => null,
            'today'     => now()->toDateString(),
            'cgPackage' => $cgPackage,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'entry_date'          => 'required|date',
            'description'         => 'required|string|max:300',
            'reference'           => 'nullable|string|max:100',
            'notes'               => 'nullable|string|max:2000',
            'action'              => 'required|in:borrador,aprobado',
            'lines'               => 'required|array|min:2',
            'lines.*.account_id'  => 'required|exists:accounting_accounts,id',
            'lines.*.cost_center_id' => 'nullable|exists:cost_centers,id',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.debit'       => 'required|numeric|min:0',
            'lines.*.credit'      => 'required|numeric|min:0',
        ]);

        $this->validateBalance($data['lines']);

        try {
            $this->periods->validateDateOrFail($data['entry_date']);
        } catch (AccountingPeriodException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $cgPackage = AccountingPackage::where('code', 'CG')->where('is_active', true)->first();

        $entry = DB::transaction(function () use ($data, $request, $cgPackage) {
            $number = JournalEntry::nextNumber($cgPackage);

            $entry = JournalEntry::create([
                'entry_number'          => $number,
                'entry_date'            => $data['entry_date'],
                'description'           => $data['description'],
                'reference'             => $data['reference'] ?? null,
                'notes'                 => $data['notes'] ?? null,
                'status'                => $data['action'],
                'created_by'            => $request->user()?->id,
                'approved_by'           => $data['action'] === 'aprobado' ? $request->user()?->id : null,
                'approved_at'           => $data['action'] === 'aprobado' ? now() : null,
                'accounting_package_id' => $cgPackage?->id,
                'source_type'           => null,
                'source_id'             => null,
                'source_document'       => $data['reference'] ?? null,
            ]);

            foreach ($data['lines'] as $i => $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'cost_center_id'   => $line['cost_center_id'] ?? $cgPackage?->cost_center_id,
                    'description'      => $line['description'] ?? null,
                    'debit'            => round((float) $line['debit'], 2),
                    'credit'           => round((float) $line['credit'], 2),
                    'sort_order'       => $i,
                ]);
            }

            return $entry;
        });

        return response()->json([
            'success'  => true,
            'message'  => $data['action'] === 'aprobado' ? 'Asiento aprobado.' : 'Borrador guardado.',
            'redirect' => route('admin.accounting.diario.show', $entry),
        ]);
    }

    public function show(JournalEntry $entry)
    {
        $entry->load(['lines.account', 'lines.costCenter', 'creator', 'approver', 'accountingPackage.costCenter']);
        return view('admin.accounting.diario-ver', compact('entry'));
    }

    public function edit(JournalEntry $entry)
    {
        if (! $entry->isEditable()) {
            return redirect()->route('admin.accounting.diario.show', $entry)
                ->with('error', 'Solo se pueden editar asientos en borrador.');
        }

        $accounts  = AccountingAccount::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type', 'nature']);
        $cgPackage = AccountingPackage::where('code', 'CG')->first();
        $costCenters = CostCenter::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $entry->load('lines');

        return view('admin.accounting.diario-nuevo', [
            'accounts'  => $accounts,
            'costCenters' => $costCenters,
            'entry'     => $entry,
            'today'     => $entry->entry_date->toDateString(),
            'cgPackage' => $cgPackage,
        ]);
    }

    public function update(Request $request, JournalEntry $entry)
    {
        if (! $entry->isEditable()) {
            return response()->json(['success' => false, 'message' => 'Solo se pueden editar asientos en borrador.'], 422);
        }

        $data = $request->validate([
            'entry_date'          => 'required|date',
            'description'         => 'required|string|max:300',
            'reference'           => 'nullable|string|max:100',
            'notes'               => 'nullable|string|max:2000',
            'action'              => 'required|in:borrador,aprobado',
            'lines'               => 'required|array|min:2',
            'lines.*.account_id'  => 'required|exists:accounting_accounts,id',
            'lines.*.cost_center_id' => 'nullable|exists:cost_centers,id',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.debit'       => 'required|numeric|min:0',
            'lines.*.credit'      => 'required|numeric|min:0',
        ]);

        $this->validateBalance($data['lines']);

        try {
            $this->periods->validateDateOrFail($data['entry_date']);
        } catch (AccountingPeriodException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        DB::transaction(function () use ($data, $entry, $request) {
            $entry->update([
                'entry_date'     => $data['entry_date'],
                'description'    => $data['description'],
                'reference'      => $data['reference'] ?? null,
                'notes'          => $data['notes'] ?? null,
                'status'         => $data['action'],
                'source_document' => $data['reference'] ?? $entry->source_document,
                'approved_by'    => $data['action'] === 'aprobado' ? $request->user()?->id : null,
                'approved_at'    => $data['action'] === 'aprobado' ? now() : null,
            ]);

            $entry->lines()->delete();

            foreach ($data['lines'] as $i => $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'cost_center_id'   => $line['cost_center_id'] ?? null,
                    'description'      => $line['description'] ?? null,
                    'debit'            => round((float) $line['debit'], 2),
                    'credit'           => round((float) $line['credit'], 2),
                    'sort_order'       => $i,
                ]);
            }
        });

        return response()->json([
            'success'  => true,
            'message'  => $data['action'] === 'aprobado' ? 'Asiento aprobado.' : 'Borrador actualizado.',
            'redirect' => route('admin.accounting.diario.show', $entry),
        ]);
    }

    public function approve(Request $request, JournalEntry $entry)
    {
        if ($entry->status !== 'borrador') {
            return response()->json(['success' => false, 'message' => 'Solo se pueden aprobar asientos en borrador.'], 422);
        }

        $entry->update([
            'status'      => 'aprobado',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Asiento aprobado y publicado en el diario.']);
    }

    public function annul(Request $request, JournalEntry $entry)
    {
        if ($entry->status !== 'aprobado') {
            return response()->json(['success' => false, 'message' => 'Solo se pueden anular asientos aprobados.'], 422);
        }

        $data = $request->validate([
            'motivo' => 'required|string|min:5|max:300',
        ]);

        DB::transaction(function () use ($entry, $data, $request) {
            $entry->update([
                'status' => 'anulado',
                'notes'  => ($entry->notes ? $entry->notes . "\n" : '') . 'Anulado: ' . $data['motivo'],
            ]);

            // Usa el mismo paquete que el asiento original para el número de reversión
            $package = $entry->accountingPackage ?? AccountingPackage::where('code', 'CG')->first();

            $reversal = JournalEntry::create([
                'entry_number'          => JournalEntry::nextNumber($package),
                'entry_date'            => now()->toDateString(),
                'description'           => 'REVERSIÓN: ' . $entry->description,
                'reference'             => $entry->entry_number,
                'status'                => 'aprobado',
                'created_by'            => $request->user()?->id,
                'approved_by'           => $request->user()?->id,
                'approved_at'           => now(),
                'notes'                 => 'Asiento de reversión automático por anulación de ' . $entry->entry_number . '. Motivo: ' . $data['motivo'],
                'accounting_package_id' => $entry->accounting_package_id,
                'source_type'           => $entry->source_type,
                'source_id'             => $entry->source_id,
                'source_document'       => $entry->source_document,
            ]);

            foreach ($entry->lines as $i => $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversal->id,
                    'account_id'       => $line->account_id,
                    'cost_center_id'   => $line->cost_center_id,
                    'description'      => $line->description,
                    'debit'            => $line->credit,
                    'credit'           => $line->debit,
                    'sort_order'       => $i,
                ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Asiento anulado. Se generó el asiento de reversión automáticamente.']);
    }

    public function destroy(JournalEntry $entry)
    {
        if (! $entry->isEditable()) {
            return response()->json(['success' => false, 'message' => 'Solo se pueden eliminar borradores.'], 422);
        }

        $entry->delete();

        return response()->json(['success' => true, 'message' => 'Borrador eliminado.']);
    }

    private function validateBalance(array $lines): void
    {
        $totalDebit  = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

        if (abs($totalDebit - $totalCredit) > 0.01) {
            abort(422, "El asiento no cuadra: Debe \${$totalDebit} ≠ Haber \${$totalCredit}. Diferencia: $" . number_format(abs($totalDebit - $totalCredit), 2));
        }

        if ($totalDebit <= 0) {
            abort(422, 'El asiento debe tener al menos un monto en Debe y uno en Haber.');
        }

        foreach ($lines as $i => $line) {
            $d = (float) $line['debit'];
            $c = (float) $line['credit'];
            if ($d > 0 && $c > 0) {
                abort(422, 'Partida ' . ($i + 1) . ': una línea no puede tener monto simultáneo en Debe y Haber.');
            }
            if ($d == 0 && $c == 0) {
                abort(422, 'Partida ' . ($i + 1) . ': debe ingresar monto en Debe o Haber.');
            }
        }
    }
}
