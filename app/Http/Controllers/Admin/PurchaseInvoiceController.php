<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\PurchaseInvoiceMailboxImporter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $supplierId = $request->query('supplier_id');

        return view('admin.purchase-invoices.index', [
            'invoices' => PurchaseInvoice::with(['supplier', 'customer'])
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

    public function extract(PurchaseInvoiceMailboxImporter $importer)
    {
        try {
            $summary = $importer->import();
        } catch (RuntimeException $exception) {
            return back()->withErrors($exception->getMessage());
        }

        $message = sprintf(
            'Extraccion finalizada: %d factura(s) importada(s), %d duplicada(s), %d adjunto(s) JSON revisado(s).',
            $summary['imported'],
            $summary['duplicates'],
            $summary['attachments']
        );

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
