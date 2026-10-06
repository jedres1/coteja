<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Traits\ExportsCsv;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\BillingInvoicePayment;
use App\Models\PurchaseInvoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class BankTransactionController extends Controller {

    use ExportsCsv;

    // ── Legacy index (keep for backward compat) ──────────────────────────────
    public function index() {
        return redirect()->route('admin.bank-transactions.transactions');
    }

    public function store(Request $request) {
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'bank_account'     => ['nullable', 'string', 'max:200'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'reference'        => ['nullable', 'string', 'max:200'],
            'notes'            => ['nullable', 'string', 'max:2000'],
            'invoice_ids'      => ['required', 'array', 'min:1'],
            'invoice_ids.*'    => ['exists:purchase_invoices,id'],
        ]);

        $transaction = BankTransaction::create(Arr::except($data, ['invoice_ids']));

        $invoices = PurchaseInvoice::findMany($data['invoice_ids']);
        foreach ($invoices as $invoice) {
            $transaction->purchaseInvoices()->attach($invoice->id, ['amount_applied' => $invoice->total]);
            $invoice->update(['payment_status' => 'paid']);
        }

        return back()->with('status', "Transacción registrada. {$invoices->count()} factura(s) marcadas como pagadas.");
    }

    // ── Bank accounts ─────────────────────────────────────────────────────────
    public function accounts() {
        $accounts = BankAccount::withCount(['bankTransactions', 'billingPayments'])
            ->orderBy('name')->get();
        return view('admin.bank-transactions.accounts', compact('accounts'));
    }

    public function storeAccount(Request $request) {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:150'],
            'bank_name'       => ['required', 'string', 'max:100'],
            'account_number'  => ['nullable', 'string', 'max:50'],
            'account_type'    => ['required', 'in:corriente,ahorros,otro'],
            'currency'        => ['required', 'string', 'max:3'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_active'       => ['nullable', 'boolean'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ]);
        BankAccount::create($data);
        return back()->with('status', 'Cuenta bancaria creada.');
    }

    public function updateAccount(Request $request, BankAccount $account) {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'bank_name'      => ['required', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'account_type'   => ['required', 'in:corriente,ahorros,otro'],
            'currency'       => ['required', 'string', 'max:3'],
            'is_active'      => ['nullable'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $account->update($data);
        return back()->with('status', 'Cuenta actualizada.');
    }

    public function destroyAccount(BankAccount $account) {
        if ($account->bankTransactions()->count() || $account->billingPayments()->count()) {
            return back()->withErrors(['No se puede eliminar: la cuenta tiene transacciones asociadas.']);
        }
        $account->delete();
        return back()->with('status', 'Cuenta eliminada.');
    }

    // ── Transactions ──────────────────────────────────────────────────────────
    public function transactions(Request $request) {
        $from = $request->input('from', today()->toDateString());
        $to   = $request->input('to', today()->toDateString());
        $bankAccountId = $request->input('bank_account_id');
        $typeFilter = $request->input('type');

        // Bank transactions (purchase invoices + manual)
        $btQuery = BankTransaction::with(['bankAccount', 'purchaseInvoices.supplier'])
            ->whereBetween('transaction_date', [$from, $to]);
        if ($bankAccountId) $btQuery->where('bank_account_id', $bankAccountId);
        if ($typeFilter)    $btQuery->where('type', $typeFilter);

        $bankTxs = $btQuery->get()->map(function ($tx) {
            $docs = $tx->purchaseInvoices->map(fn($inv) => $inv->invoice_number)->join(', ');
            return [
                'raw_id'         => $tx->id,
                'id'             => 'bt_' . $tx->id,
                'source'         => $tx->purchaseInvoices->isNotEmpty() ? 'compras' : 'manual',
                'date'           => $tx->transaction_date->format('Y-m-d'),
                'type'           => $tx->type ?? 'pago',
                'amount'         => (float) $tx->amount,
                'bank_account'   => $tx->bankAccount?->name ?? ($tx->bank_account ?? '—'),
                'reference'      => $tx->reference,
                'notes'          => $tx->notes ?? '',
                'document'       => $docs ?: null,
                'document_label' => $docs ? 'Factura(s) compra' : null,
                'is_reconciled'  => (bool) $tx->is_reconciled,
            ];
        });

        // Billing invoice payments
        $bipQuery = BillingInvoicePayment::with(['invoice', 'bankAccount'])
            ->whereBetween(\DB::raw('DATE(registered_at)'), [$from, $to]);
        if ($bankAccountId) $bipQuery->where('bank_account_id', $bankAccountId);
        if ($typeFilter && in_array($typeFilter, ['deposito','pago','transferencia','cheque','otro'])) {
            $bipQuery->where('method', $typeFilter);
        }

        $billingPayments = $bipQuery->get()->map(function ($payment) {
            return [
                'raw_id'         => $payment->id,
                'id'             => 'bp_' . $payment->id,
                'source'         => 'facturacion',
                'date'           => $payment->registered_at->format('Y-m-d'),
                'type'           => $payment->method ?? 'pago',
                'amount'         => (float) $payment->amount,
                'bank_account'   => $payment->bankAccount?->name ?? '—',
                'reference'      => $payment->reference,
                'notes'          => $payment->notes ?? '',
                'document'       => $payment->invoice?->number_control,
                'document_label' => 'Factura venta',
                'is_reconciled'  => (bool) $payment->is_reconciled,
            ];
        });

        $transactions = $bankTxs->concat($billingPayments)->sortByDesc('date')->values();
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('name')->get();

        return view('admin.bank-transactions.transactions', compact('transactions', 'bankAccounts', 'from', 'to', 'bankAccountId'));
    }

    public function storeTransaction(Request $request) {
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'bank_account_id'  => ['required', 'exists:bank_accounts,id'],
            'type'             => ['required', 'in:deposito,pago,transferencia,cheque,otro'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'reference'        => ['nullable', 'string', 'max:200'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);
        BankTransaction::create($data);
        return back()->with('status', 'Transacción registrada correctamente.');
    }

    // ── Reconciliations ───────────────────────────────────────────────────────
    public function reconciliations() {
        $bankAccounts    = BankAccount::where('is_active', true)->orderBy('name')->get();
        $reconciliations = BankReconciliation::with('bankAccount')
            ->orderByDesc('period_year')->orderByDesc('period_month')->get();
        return view('admin.bank-transactions.reconciliations', compact('bankAccounts', 'reconciliations'));
    }

    public function storeReconciliation(Request $request) {
        $data = $request->validate([
            'bank_account_id'   => ['required', 'exists:bank_accounts,id'],
            'period_year'       => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month'      => ['required', 'integer', 'min:1', 'max:12'],
            'statement_balance' => ['nullable', 'numeric', 'min:0'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ]);

        $existing = BankReconciliation::where([
            'bank_account_id' => $data['bank_account_id'],
            'period_year'     => $data['period_year'],
            'period_month'    => $data['period_month'],
        ])->exists();

        if ($existing) {
            return back()->withErrors(['Ya existe una conciliación para ese mes y cuenta.']);
        }

        $recon = BankReconciliation::create($data);
        return redirect()->route('admin.bank-transactions.reconciliations.show', $recon)
            ->with('status', 'Conciliación creada.');
    }

    public function showReconciliation(BankReconciliation $reconciliation) {
        $account = $reconciliation->bankAccount;
        $from = Carbon::create($reconciliation->period_year, $reconciliation->period_month, 1)->startOfMonth()->toDateString();
        $to   = Carbon::create($reconciliation->period_year, $reconciliation->period_month, 1)->endOfMonth()->toDateString();

        $bankTxs = BankTransaction::with(['purchaseInvoices.supplier'])
            ->where('bank_account_id', $account->id)
            ->whereBetween('transaction_date', [$from, $to])
            ->get()->map(function ($tx) {
                $docs = $tx->purchaseInvoices->map(fn($inv) => $inv->invoice_number)->join(', ');
                return [
                    'raw_id'         => $tx->id,
                    'source'         => $tx->purchaseInvoices->isNotEmpty() ? 'compras' : 'manual',
                    'date'           => $tx->transaction_date->format('Y-m-d'),
                    'type'           => $tx->type ?? 'pago',
                    'amount'         => (float) $tx->amount,
                    'reference'      => $tx->reference,
                    'document'       => $docs ?: null,
                    'document_label' => $docs ? 'Factura(s) compra' : null,
                    'is_reconciled'  => (bool) $tx->is_reconciled,
                ];
            });

        $billingPayments = BillingInvoicePayment::with('invoice')
            ->where('bank_account_id', $account->id)
            ->whereBetween(\DB::raw('DATE(registered_at)'), [$from, $to])
            ->get()->map(function ($payment) {
                return [
                    'raw_id'         => $payment->id,
                    'source'         => 'facturacion',
                    'date'           => $payment->registered_at->format('Y-m-d'),
                    'type'           => $payment->method ?? 'pago',
                    'amount'         => (float) $payment->amount,
                    'reference'      => $payment->reference,
                    'document'       => $payment->invoice?->number_control,
                    'document_label' => 'Factura venta',
                    'is_reconciled'  => (bool) $payment->is_reconciled,
                ];
            });

        $transactions = $bankTxs->concat($billingPayments)->sortByDesc('date')->values();

        return view('admin.bank-transactions.reconciliation-detail', compact('reconciliation', 'transactions'));
    }

    public function uploadStatement(Request $request, BankReconciliation $reconciliation) {
        $request->validate([
            'statement_file'    => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'statement_balance' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($reconciliation->statement_file_path) {
            Storage::disk('public')->delete($reconciliation->statement_file_path);
        }

        $path = $request->file('statement_file')->store('bank-statements', 'public');
        $reconciliation->update([
            'statement_file_path' => $path,
            'statement_balance'   => $request->input('statement_balance'),
        ]);

        return back()->with('status', 'Estado de cuenta cargado correctamente.');
    }

    public function toggleReconcile(Request $request, BankReconciliation $reconciliation) {
        $request->validate([
            'tx_id'     => ['required'],
            'tx_source' => ['required', 'in:compras,manual,facturacion'],
            'reconciled'=> ['required', 'boolean'],
        ]);

        $reconciled = (bool) $request->input('reconciled');
        $reconId    = $reconciled ? $reconciliation->id : null;

        if (in_array($request->input('tx_source'), ['compras', 'manual'])) {
            BankTransaction::where('id', $request->input('tx_id'))
                ->update(['is_reconciled' => $reconciled, 'reconciliation_id' => $reconId]);
        } else {
            BillingInvoicePayment::where('id', $request->input('tx_id'))
                ->update(['is_reconciled' => $reconciled, 'reconciliation_id' => $reconId]);
        }

        return back()->with('status', $reconciled ? 'Transacción marcada como conciliada.' : 'Marca de conciliación removida.');
    }

    public function completeReconciliation(BankReconciliation $reconciliation) {
        $reconciliation->update(['status' => 'completado', 'completed_at' => now()]);
        return back()->with('status', 'Conciliación marcada como completada.');
    }

    public function exportTransactions(Request $request) {
        $from = $request->input('from', today()->toDateString());
        $to   = $request->input('to', today()->toDateString());
        $bankAccountId = $request->input('bank_account_id');

        $btQuery = BankTransaction::with(['bankAccount', 'purchaseInvoices.supplier'])
            ->whereBetween('transaction_date', [$from, $to]);
        if ($bankAccountId) {
            $btQuery->where('bank_account_id', $bankAccountId);
        }

        $bankTxs = $btQuery->get()->map(fn ($tx) => [
            $tx->transaction_date->format('Y-m-d'),
            $tx->purchaseInvoices->isNotEmpty() ? 'compras' : 'manual',
            $tx->type ?? 'pago',
            $tx->bankAccount?->name ?? ($tx->bank_account ?? ''),
            $tx->reference,
            $tx->purchaseInvoices->map(fn ($inv) => $inv->invoice_number)->join(', '),
            number_format($tx->amount, 2),
        ]);

        $bipQuery = BillingInvoicePayment::with(['invoice', 'bankAccount'])
            ->whereBetween(\DB::raw('DATE(registered_at)'), [$from, $to]);
        if ($bankAccountId) {
            $bipQuery->where('bank_account_id', $bankAccountId);
        }

        $billingPayments = $bipQuery->get()->map(fn ($payment) => [
            $payment->registered_at->format('Y-m-d'),
            'facturacion',
            $payment->method ?? 'pago',
            $payment->bankAccount?->name ?? '',
            $payment->reference,
            $payment->invoice?->number_control,
            number_format($payment->amount, 2),
        ]);

        $rows = $bankTxs->concat($billingPayments)->sortByDesc(fn ($r) => $r[0]);

        return $this->streamCsv(
            'transacciones-' . $from . '-' . $to . '.csv',
            ['Fecha', 'Origen', 'Tipo', 'Cuenta Bancaria', 'Referencia', 'Documento', 'Monto'],
            $rows
        );
    }
}
