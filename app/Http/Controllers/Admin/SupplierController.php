<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveSupplierRequest;
use App\Models\Supplier;
use App\Traits\ExportsCsv;
use App\Traits\HasTenantScope;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    use ExportsCsv, HasTenantScope;

    public function index(Request $request)
    {
        $tenantId = $this->tenantId();
        $search   = trim((string) $request->query('search', ''));
        $geography = json_decode(file_get_contents(public_path('catalogs/division-geografica.json')), true) ?: [];

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => Supplier::withCount('purchaseInvoices')
                ->whereIn('company_id', $tenantId !== null ? [$tenantId] : [])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%")
                            ->orWhere('nrc', 'like', "%{$search}%")
                            ->orWhere('business_activity', 'like', "%{$search}%")
                            ->orWhere('billing_email', 'like', "%{$search}%")
                            ->orWhere('billing_phone', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'search'    => $search,
            'geography' => $geography,
        ]);
    }

    public function store(SaveSupplierRequest $request)
    {
        $tenantId = $this->tenantId();
        abort_if($tenantId === null, 403, 'Selecciona una empresa antes de registrar proveedores.');

        Supplier::create(array_merge($request->validated(), [
            'company_id' => $tenantId,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]));

        return back()->with('status', 'Proveedor creado.');
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier)
    {
        $tenantId = $this->tenantId();
        abort_unless($tenantId !== null && $supplier->company_id === $tenantId, 403);

        $supplier->update(array_merge($request->validated(), [
            'updated_by' => $request->user()?->id,
        ]));

        return back()->with('status', 'Proveedor actualizado.');
    }

    public function destroy(Supplier $supplier)
    {
        $tenantId = $this->tenantId();
        abort_unless($tenantId !== null && $supplier->company_id === $tenantId, 403);

        if ($supplier->purchaseInvoices()->exists()) {
            return back()->withErrors('No se puede eliminar un proveedor con facturas de compra registradas.');
        }

        $supplier->delete();

        return back()->with('status', 'Proveedor eliminado.');
    }

    public function export(Request $request)
    {
        $tenantId = $this->tenantId();
        $search   = trim((string) $request->query('search', ''));

        $rows = Supplier::withCount('purchaseInvoices')
            ->whereIn('company_id', $tenantId !== null ? [$tenantId] : [])
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
