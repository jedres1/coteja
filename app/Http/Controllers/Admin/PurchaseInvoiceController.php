<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\PurchaseInvoiceMailboxImporter;
use Illuminate\Http\Request;
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
        ]);
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

        return view('admin.purchase-invoices.accounts-payable', compact('invoices', 'totals', 'search'));
    }

    public function markPaid(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $data = $request->validate([
            'payment_status'   => ['required', 'in:paid,partial,pending'],
            'payment_method'   => ['nullable', 'string', 'max:100'],
            'transaction_date' => ['nullable', 'date'],
            'bank_account'     => ['nullable', 'string', 'max:200'],
            'reference'        => ['nullable', 'string', 'max:200'],
        ]);

        $purchaseInvoice->update([
            'payment_status' => $data['payment_status'],
            'payment_method' => $data['payment_method'] ?? $purchaseInvoice->payment_method,
        ]);

        if ($data['payment_status'] === 'paid' && !empty($data['transaction_date'])) {
            $transaction = \App\Models\BankTransaction::create([
                'transaction_date' => $data['transaction_date'],
                'bank_account'     => $data['bank_account'] ?? null,
                'amount'           => $purchaseInvoice->total,
                'reference'        => $data['reference'] ?? null,
            ]);
            $transaction->purchaseInvoices()->attach($purchaseInvoice->id, [
                'amount_applied' => $purchaseInvoice->total,
            ]);
        }

        return back()->with('status', 'Estado de pago actualizado.');
    }

    public function pendingApproval()
    {
        $invoices = PurchaseInvoice::with('supplier')
            ->where('status', 'extracted')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $total = PurchaseInvoice::where('status', 'extracted')->count();

        return view('admin.purchase-invoices.pending-approval', compact('invoices', 'total'));
    }

    public function approve(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->update(['status' => 'approved']);
        return back()->with('status', 'Factura aprobada y enviada a Cuentas por pagar.');
    }

    public function reject(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->update(['status' => 'rejected']);
        return back()->with('status', 'Factura rechazada.');
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
            'Extraccion finalizada: %d factura(s) importada(s), %d duplicada(s), %d adjunto(s) JSON revisado(s).',
            $summary['imported'],
            $summary['duplicates'],
            $summary['attachments']
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
