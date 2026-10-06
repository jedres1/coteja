<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSupplierRequest;
use App\Models\Supplier;
use App\Traits\ExportsCsv;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ExportsCsv;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $geography = json_decode(file_get_contents(public_path('catalogs/division-geografica.json')), true) ?: [];

        return view('admin.suppliers.index', [
            'suppliers' => Supplier::withCount('purchaseInvoices')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%")
                            ->orWhere('nrc', 'like', "%{$search}%")
                            ->orWhere('business_activity', 'like', "%{$search}%")
                            ->orWhere('activity_description', 'like', "%{$search}%")
                            ->orWhere('billing_email', 'like', "%{$search}%")
                            ->orWhere('billing_phone', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'search' => $search,
            'geography' => $geography,
        ]);
    }

    public function store(SaveSupplierRequest $request)
    {
        Supplier::create($request->validated());

        return back()->with('status', 'Proveedor creado.');
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier)
    {
        $supplier->update($request->validated());

        return back()->with('status', 'Proveedor actualizado.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseInvoices()->exists()) {
            return back()->withErrors('No se puede eliminar un proveedor con facturas de compra registradas.');
        }

        $supplier->delete();

        return back()->with('status', 'Proveedor eliminado.');
    }

    public function export(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $rows = Supplier::withCount('purchaseInvoices')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('trade_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('nrc', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get()
            ->map(fn ($s) => [
                $s->name,
                $s->trade_name,
                $s->email,
                $s->phone,
                $s->document_number,
                $s->nrc,
                $s->purchase_invoices_count,
            ]);

        return $this->streamCsv(
            'proveedores-' . now()->format('Ymd') . '.csv',
            ['Nombre', 'Nombre Comercial', 'Email', 'Teléfono', 'NIT/DUI', 'NRC', 'Facturas de Compra'],
            $rows
        );
    }
}
