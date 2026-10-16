<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AccountingPeriodException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveJournalEntryRequest;
use App\Traits\ExportsCsv;
use App\Traits\HasAccountingTenant;
use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Accounting\AccountingPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class JournalController extends Controller
{
    use ExportsCsv, HasAccountingTenant;

    public function __construct(private readonly AccountingPeriodService $periods) {}

    public function index(Request $request)
    {
        $customerId = $this->requireTenant();

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');
        $from   = $request->query('from', now()->startOfMonth()->toDateString());
        $to     = $request->query('to', now()->toDateString());

        $query = JournalEntry::forCustomer($customerId)
            ->with(['lines', 'accountingPackage'])
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
            'borrador' => JournalEntry::forCustomer($customerId)->where('status', 'borrador')->count(),
            'aprobado' => JournalEntry::forCustomer($customerId)->where('status', 'aprobado')->count(),
            'anulado'  => JournalEntry::forCustomer($customerId)->where('status', 'anulado')->count(),
            'total'    => JournalEntry::forCustomer($customerId)->count(),
        ];

        return Inertia::render('Admin/Accounting/Diario', compact('entries', 'stats', 'search', 'status', 'from', 'to'));
    }

    public function create()
    {
        $customerId = $this->requireTenant();

        $accounts = AccountingAccount::forCustomer($customerId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'nature']);

        $cgPackage   = AccountingPackage::forCustomer($customerId)->where('code', 'CG')->first();
        $costCenters = CostCenter::forCustomer($customerId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('Admin/Accounting/DiarioNuevo', [
            'accounts'    => $accounts,
            'costCenters' => $costCenters,
            'entry'       => null,
            'today'       => now()->toDateString(),
            'cgPackage'   => $cgPackage,
        ]);
    }

    public function store(SaveJournalEntryRequest $request)
    {
        $customerId = $this->requireTenant();

        $data = $request->validated();

        try {
            $this->periods->validateDateOrFail($data['entry_date']);
        } catch (AccountingPeriodException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $cgPackage = AccountingPackage::forCustomer($customerId)->where('code', 'CG')->where('is_active', true)->first();

        $entry = DB::transaction(function () use ($data, $request, $cgPackage, $customerId) {
            $number = JournalEntry::nextNumber($cgPackage, $customerId);

            $entry = JournalEntry::create([
                'customer_id'           => $customerId,
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
        $customerId = $this->requireTenant();
        abort_unless($entry->customer_id === $customerId, 403);

        $entry->load(['lines.account', 'lines.costCenter', 'creator', 'approver', 'accountingPackage.costCenter']);
        return Inertia::render('Admin/Accounting/DiarioVer', compact('entry'));
    }

    public function edit(JournalEntry $entry)
    {
        $customerId = $this->requireTenant();
        abort_unless($entry->customer_id === $customerId, 403);

        if (! $entry->isEditable()) {
            return redirect()->route('admin.accounting.diario.show', $entry)
                ->with('error', 'Solo se pueden editar asientos en borrador.');
        }

        $accounts    = AccountingAccount::forCustomer($customerId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'type', 'nature']);
        $cgPackage   = AccountingPackage::forCustomer($customerId)->where('code', 'CG')->first();
        $costCenters = CostCenter::forCustomer($customerId)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $entry->load('lines');

        return Inertia::render('Admin/Accounting/DiarioNuevo', [
            'accounts'    => $accounts,
            'costCenters' => $costCenters,
            'entry'       => $entry,
            'today'       => $entry->entry_date->toDateString(),
            'cgPackage'   => $cgPackage,
        ]);
    }

    public function update(SaveJournalEntryRequest $request, JournalEntry $entry)
    {
        $customerId = $this->requireTenant();
        abort_unless($entry->customer_id === $customerId, 403);

        $this->authorize('update', $entry);

        if (! $entry->isEditable()) {
            return response()->json(['success' => false, 'message' => 'Solo se pueden editar asientos en borrador.'], 422);
        }

        $data = $request->validated();

        try {
            $this->periods->validateDateOrFail($data['entry_date']);
        } catch (AccountingPeriodException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        DB::transaction(function () use ($data, $entry, $request) {
            $entry->update([
                'entry_date'      => $data['entry_date'],
                'description'     => $data['description'],
                'reference'       => $data['reference'] ?? null,
                'notes'           => $data['notes'] ?? null,
                'status'          => $data['action'],
                'source_document' => $data['reference'] ?? $entry->source_document,
                'approved_by'     => $data['action'] === 'aprobado' ? $request->user()?->id : null,
                'approved_at'     => $data['action'] === 'aprobado' ? now() : null,
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
        $customerId = $this->requireTenant();
        abort_unless($entry->customer_id === $customerId, 403);

        $this->authorize('approve', $entry);

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
        $customerId = $this->requireTenant();
        abort_unless($entry->customer_id === $customerId, 403);

        $this->authorize('annul', $entry);

        if ($entry->status !== 'aprobado') {
            return response()->json(['success' => false, 'message' => 'Solo se pueden anular asientos aprobados.'], 422);
        }

        $data = $request->validate([
            'motivo' => 'required|string|min:5|max:300',
        ]);

        DB::transaction(function () use ($entry, $data, $request, $customerId) {
            $entry->update([
                'status' => 'anulado',
                'notes'  => ($entry->notes ? $entry->notes . "\n" : '') . 'Anulado: ' . $data['motivo'],
            ]);

            $package = $entry->accountingPackage
                ?? AccountingPackage::forCustomer($customerId)->where('code', 'CG')->first();

            $reversal = JournalEntry::create([
                'customer_id'           => $customerId,
                'entry_number'          => JournalEntry::nextNumber($package, $customerId),
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
        $customerId = $this->requireTenant();
        abort_unless($entry->customer_id === $customerId, 403);

        $this->authorize('delete', $entry);

        if (! $entry->isEditable()) {
            return response()->json(['success' => false, 'message' => 'Solo se pueden eliminar borradores.'], 422);
        }

        $entry->delete();

        return response()->json(['success' => true, 'message' => 'Borrador eliminado.']);
    }

    public function export(Request $request)
    {
        $customerId = $this->requireTenant();

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');
        $from   = $request->query('from', now()->startOfMonth()->toDateString());
        $to     = $request->query('to', now()->toDateString());

        $rows = JournalEntry::forCustomer($customerId)
            ->with(['lines', 'creator'])
            ->when($from,   fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to,     fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('entry_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('reference',   'like', "%{$search}%");
            }))
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->map(fn ($e) => [
                $e->entry_number,
                $e->entry_date?->format('Y-m-d'),
                $e->description,
                $e->reference,
                number_format($e->lines->sum('debit'), 2),
                $e->status,
                $e->creator?->name,
            ]);

        return $this->streamCsv(
            'diario-' . now()->format('Ymd') . '.csv',
            ['No. Asiento', 'Fecha', 'Descripción', 'Referencia', 'Débito Total', 'Estado', 'Creado por'],
            $rows
        );
    }
}
