<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AccountingPeriodException;
use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\BankAccount;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\Accounting\AccountingEntryService;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\PurchaseInvoiceMailboxImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class PurchaseInvoiceController extends Controller
{
    public function settingsIndex()
    {
        $settings = BillingSetting::allAsArray();
        $env = config('services.purchase_invoice_mailbox');

        return view('admin.purchase-invoices.settings', [
            'host'       => $settings['mailbox_host']        ?? $env['host'],
            'port'       => $settings['mailbox_port']        ?? $env['port'],
            'username'   => $settings['mailbox_username']    ?? $env['username'],
            'password'   => $settings['mailbox_password']    ?? $env['password'] ?? '',
            'mailbox'    => $settings['mailbox_mailbox']     ?? $env['mailbox'],
            'onlyUnseen' => $settings['mailbox_only_unseen'] ?? $env['only_unseen'],
            'limit'      => $settings['mailbox_limit']       ?? $env['limit'],
            'accountingAccounts' => AccountingAccount::where('is_active', true)->orderBy('code')->get(),
            'costCenters' => CostCenter::where('is_active', true)->orderBy('code')->get(),
            'purchasePackage' => AccountingPackage::where('code', 'CP')->first(),
            'payablePackage' => AccountingPackage::where('code', 'CXP')->first(),
            'missingPurchaseEntries' => PurchaseInvoice::where('status', 'approved')->whereDoesntHave('journalEntry')->count(),
            'missingPayableEntries' => PurchaseInvoice::where('payment_status', 'paid')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('journal_entries')
                    ->whereColumn('journal_entries.source_id', 'purchase_invoices.id')
                    ->where('journal_entries.source_type', 'purchase_payment'))
                ->count(),
        ]);
    }

    public function accountingSettingsUpdate(Request $request)
    {
        $activeAccount = Rule::exists('accounting_accounts', 'id')->where('is_active', true);
        $activeCostCenter = Rule::exists('cost_centers', 'id')->where('is_active', true);
        $data = $request->validate([
            'purchase_debit_account_id' => ['required', 'integer', $activeAccount],
            'purchase_credit_account_id' => ['required', 'integer', $activeAccount],
            'purchase_cost_center_id' => ['nullable', 'integer', $activeCostCenter],
            'payable_debit_account_id' => ['required', 'integer', $activeAccount],
            'payable_credit_account_id' => ['required', 'integer', $activeAccount],
            'payable_cost_center_id' => ['nullable', 'integer', $activeCostCenter],
        ]);

        DB::transaction(function () use ($data) {
            AccountingPackage::where('code', 'CP')->update([
                'debit_account_id' => $data['purchase_debit_account_id'],
                'credit_account_id' => $data['purchase_credit_account_id'],
                'cost_center_id' => $data['purchase_cost_center_id'] ?? null,
                'is_active' => true,
                'type' => 'automatico',
            ]);
            AccountingPackage::where('code', 'CXP')->update([
                'debit_account_id' => $data['payable_debit_account_id'],
                'credit_account_id' => $data['payable_credit_account_id'],
                'cost_center_id' => $data['payable_cost_center_id'] ?? null,
                'is_active' => true,
                'type' => 'automatico',
            ]);
        });

        return back()->with('status', 'Parámetros contables de compras y cuentas por pagar guardados.');
    }

    public function generateMissingEntries(AccountingEntryService $accounting, AccountingPeriodService $periods)
    {
        $purchaseEntries = 0;
        $payableEntries = 0;
        $failures = 0;

        $invoices = PurchaseInvoice::with('supplier')
            ->where('status', 'approved')
            ->whereDoesntHave('journalEntry')
            ->get();

        foreach ($invoices as $invoice) {
            try {
                $periods->validateDateOrFail($invoice->purchase_date ?? now());
                if ($accounting->createFromPurchase($invoice, request()->user()?->id)) {
                    $purchaseEntries++;
                }
            } catch (\Throwable) {
                $failures++;
            }
        }

        $paidInvoices = PurchaseInvoice::with(['supplier', 'bankTransactions'])
            ->where('payment_status', 'paid')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('journal_entries')
                ->whereColumn('journal_entries.source_id', 'purchase_invoices.id')
                ->where('journal_entries.source_type', 'purchase_payment'))
            ->get();

        foreach ($paidInvoices as $invoice) {
            try {
                $paymentTransaction = $invoice->bankTransactions->sortByDesc('transaction_date')->first();
                $accounting->createFromPurchasePayment(
                    $invoice,
                    request()->user()?->id,
                    $paymentTransaction?->transaction_date?->toDateString() ?? $invoice->purchase_date?->toDateString(),
                    $paymentTransaction?->reference,
                );
                $payableEntries++;
            } catch (\Throwable) {
                $failures++;
            }
        }

        $message = "Asientos generados: {$purchaseEntries} de compras y {$payableEntries} de pagos.";
        if ($failures > 0) {
            $message .= " {$failures} registro(s) no se procesaron; revise la configuración contable y los períodos.";
        }

        return back()->with('status', $message);
    }

    public function settingsUpdate(Request $request)
    {
        $data = $request->validate([
            'mailbox_host'        => ['required', 'string', 'max:255'],
            'mailbox_port'        => ['required', 'integer', 'min:1', 'max:65535'],
            'mailbox_username'    => ['required', 'string', 'max:255'],
            'mailbox_password'    => ['nullable', 'string', 'max:255'],
            'mailbox_mailbox'     => ['required', 'string', 'max:100'],
            'mailbox_only_unseen' => ['nullable', 'boolean'],
            'mailbox_limit'       => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            if ($key === 'mailbox_password' && blank($value)) {
                continue;
            }
            BillingSetting::put($key, $value ?? false);
        }

        if (!array_key_exists('mailbox_only_unseen', $data)) {
            BillingSetting::put('mailbox_only_unseen', false);
        }

        return back()->with('status', 'Configuración de correo actualizada.');
    }

    public function accountsPayable(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $baseQuery = fn () => PurchaseInvoice::whereIn('payment_status', ['pending', 'partial'])
            ->whereNotIn('status', ['extracted', 'rejected'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('total', 'like', "%{$search}%")
                      ->orWhereHas('supplier', fn ($q) => $q
                          ->where('name', 'like', "%{$search}%")
                          ->orWhere('document_number', 'like', "%{$search}%")
                          ->orWhere('nrc', 'like', "%{$search}%")
                      );
                });
            });

        $totals = [
            'total'     => (clone $baseQuery())->sum('total'),
            'invoices'  => (clone $baseQuery())->count(),
            'suppliers' => (clone $baseQuery())->distinct('supplier_id')->count('supplier_id'),
        ];

        $invoices = $baseQuery()
            ->with('supplier')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $bankAccounts = BankAccount::where('is_active', true)->orderBy('bank_name')->orderBy('name')->get();

        return view('admin.purchase-invoices.accounts-payable', compact('invoices', 'totals', 'search', 'bankAccounts'));
    }

    public function markPaid(Request $request, PurchaseInvoice $purchaseInvoice, AccountingEntryService $accounting)
    {
        $data = $request->validate([
            'payment_status'   => ['required', 'in:paid,partial,pending'],
            'payment_method'   => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['required_if:payment_status,paid', 'nullable', 'date'],
            'bank_account_id'  => [
                'required_if:payment_status,paid',
                'nullable',
                'integer',
                Rule::exists('bank_accounts', 'id')->where('is_active', true),
            ],
            'reference'        => ['nullable', 'string', 'max:200'],
        ]);

        try {
            DB::transaction(function () use ($data, $purchaseInvoice, $accounting, $request) {
                $paymentEntryExists = JournalEntry::where('source_type', 'purchase_payment')
                    ->where('source_id', $purchaseInvoice->id)
                    ->exists();

                if ($data['payment_status'] === 'paid' && ! $paymentEntryExists) {
                    $accounting->createFromPurchasePayment(
                        $purchaseInvoice->load('supplier'),
                        $request->user()?->id,
                        $data['transaction_date'] ?? null,
                        $data['reference'] ?? null,
                    );
                }

                $purchaseInvoice->update([
                    'payment_status' => $data['payment_status'],
                    'payment_method' => $data['payment_method'] ?? $purchaseInvoice->payment_method,
                ]);

                if ($data['payment_status'] === 'paid' && ! $paymentEntryExists && ! empty($data['transaction_date'])) {
                    $bankAccount = BankAccount::findOrFail($data['bank_account_id']);
                    $transaction = \App\Models\BankTransaction::create([
                        'bank_account_id' => $bankAccount->id,
                        'transaction_date' => $data['transaction_date'],
                        'bank_account'     => trim($bankAccount->bank_name.' / '.$bankAccount->name.' '.($bankAccount->account_number ?? '')),
                        'amount'           => $purchaseInvoice->total,
                        'reference'        => $data['reference'] ?? null,
                    ]);
                    $transaction->purchaseInvoices()->attach($purchaseInvoice->id, [
                        'amount_applied' => $purchaseInvoice->total,
                    ]);
                }
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return back()->with('status', 'Estado de pago actualizado.');
    }

    public function pendingApproval(Request $request)
    {
        $showAll = $request->boolean('ver_todas');

        $invoices = PurchaseInvoice::with('supplier')
            ->when(!$showAll, fn ($q) => $q->where('status', 'extracted'))
            ->latest('purchase_date')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $total = PurchaseInvoice::where('status', 'extracted')->count();

        return view('admin.purchase-invoices.pending-approval', compact('invoices', 'total', 'showAll'));
    }

    public function approve(PurchaseInvoice $purchaseInvoice, Request $request, AccountingEntryService $accounting, AccountingPeriodService $periods)
    {
        try {
            $periods->validateDateOrFail($purchaseInvoice->purchase_date ?? now());
        } catch (AccountingPeriodException $e) {
            return back()->withErrors($e->getMessage());
        }

        try {
            DB::transaction(function () use ($accounting, $purchaseInvoice, $request) {
                $accounting->createFromPurchase($purchaseInvoice->load('supplier'), $request->user()?->id);
                $purchaseInvoice->update(['status' => 'approved']);
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        return back()->with('status', 'Factura aprobada y enviada a Cuentas por pagar.');
    }

    public function reject(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->update(['status' => 'rejected']);
        return back()->with('status', 'Factura rechazada.');
    }

    public function approveBulk(Request $request, AccountingEntryService $accounting, AccountingPeriodService $periods)
    {
        $ids = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'exists:purchase_invoices,id'],
        ])['ids'];

        $invoices = PurchaseInvoice::with('supplier')
            ->whereIn('id', $ids)
            ->where('status', 'extracted')
            ->get();

        $blocked = 0;
        $updated = 0;

        foreach ($invoices as $invoice) {
            try {
                $periods->validateDateOrFail($invoice->purchase_date ?? now());
            } catch (AccountingPeriodException $e) {
                $blocked++;
                continue;
            }

            try {
                DB::transaction(function () use ($accounting, $invoice, $request) {
                    $accounting->createFromPurchase($invoice, $request->user()?->id);
                    $invoice->update(['status' => 'approved']);
                });
                $updated++;
            } catch (\Throwable) {
                $blocked++;
            }
        }

        $msg = "{$updated} factura(s) aprobada(s) y enviadas a Cuentas por pagar.";
        if ($blocked > 0) {
            $msg .= " {$blocked} factura(s) no se aprobaron por período cerrado o configuración contable incompleta.";
        }

        return back()->with('status', $msg);
    }

    public function rejectBulk(Request $request)
    {
        $ids = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'exists:purchase_invoices,id'],
        ])['ids'];

        $updated = PurchaseInvoice::whereIn('id', $ids)->where('status', 'extracted')->update(['status' => 'rejected']);

        return back()->with('status', "{$updated} factura(s) rechazada(s).");
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $supplierId = $request->query('supplier_id');

        return view('admin.purchase-invoices.index', [
            'invoices' => PurchaseInvoice::with(['supplier', 'customer'])
                ->whereNotIn('status', ['extracted', 'rejected'])
                ->when($supplierId, fn ($query) => $query->where('supplier_id', $supplierId))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('invoice_number', 'like', "%{$search}%")
                            ->orWhere('payment_method', 'like', "%{$search}%")
                            ->orWhere('notes', 'like', "%{$search}%")
                            ->orWhereHas('supplier', function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('trade_name', 'like', "%{$search}%")
                                    ->orWhere('document_number', 'like', "%{$search}%")
                                    ->orWhere('nrc', 'like', "%{$search}%");
                            });
                    });
                })
                ->latest('purchase_date')
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'customers' => Customer::orderBy('name')->get(),
            'search' => $search,
            'supplierId' => $supplierId,
        ]);
    }

    public function store(Request $request)
    {
        PurchaseInvoice::create($this->validatedData($request));

        return back()->with('status', 'Factura de compra registrada.');
    }

    public function extract(Request $request, PurchaseInvoiceMailboxImporter $importer)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        try {
            $summary = $importer->import(
                $request->input('from'),
                $request->input('to'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        $message = sprintf(
            'Extracción finalizada: %d correo(s) revisado(s), %d adjunto(s) JSON, %d factura(s) importada(s), %d duplicada(s) y %d filtrada(s) por NIT/DUI.',
            $summary['messages'],
            $summary['attachments'],
            $summary['imported'],
            $summary['duplicates'],
            $summary['filtered']
        );

        if (!empty($summary['details'])) {
            $detailsText = "\n\nDetalles:\n";
            foreach ($summary['details'] as $detail) {
                $statusEmoji = $detail['status'] === 'IMPORTADA' ? '✓' : '⚠';
                $detailsText .= sprintf(
                    "%s %s | Proveedor: %s | NumControl: %s | CodGen: %s%s\n",
                    $statusEmoji,
                    $detail['status'],
                    $detail['supplier'],
                    $detail['numeroControl'] ?? 'N/A',
                    $detail['codigoGeneracion'] ?? 'N/A',
                    $detail['razon'] ? ' | Razón: '.$detail['razon'] : ''
                );
            }
            $message .= $detailsText;
        }

        if ($summary['errors']) {
            return back()
                ->with('status', $message)
                ->withErrors($summary['errors']);
        }

        return back()->with('status', $message);
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->update($this->validatedData($request, $purchaseInvoice));

        return back()->with('status', 'Factura de compra actualizada.');
    }

    public function destroy(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->delete();

        return back()->with('status', 'Factura de compra eliminada.');
    }

    private function validatedData(Request $request, ?PurchaseInvoice $purchaseInvoice = null): array
    {
        $supplierId = $request->input('supplier_id');

        return $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'document_type' => ['required', 'in:01,03,05,06,11,14,99'],
            'invoice_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('purchase_invoices', 'invoice_number')
                    ->where(fn ($query) => $query->where('supplier_id', $supplierId))
                    ->ignore($purchaseInvoice?->id),
            ],
            'purchase_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'subtotal' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'iva' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'total' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_status' => ['required', 'in:pending,paid,partial,void'],
            'status' => ['required', 'in:registered,reviewed,accounted,void'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
