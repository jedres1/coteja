<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class BankTransactionController extends Controller {
    public function index() {
        $transactions = BankTransaction::with(['purchaseInvoices.supplier'])
            ->latest('transaction_date')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $pendingInvoices = PurchaseInvoice::with('supplier')
            ->whereIn('payment_status', ['pending', 'partial'])
            ->whereNotIn('status', ['extracted', 'rejected'])
            ->orderBy('purchase_date')
            ->get();

        return view('admin.bank-transactions.index', compact('transactions', 'pendingInvoices'));
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
            $transaction->purchaseInvoices()->attach($invoice->id, [
                'amount_applied' => $invoice->total,
            ]);
            $invoice->update(['payment_status' => 'paid']);
        }

        return back()->with('status', "Transacción registrada. {$invoices->count()} factura(s) marcadas como pagadas.");
    }
}
