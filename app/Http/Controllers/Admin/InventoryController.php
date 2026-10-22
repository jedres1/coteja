<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AccountingPeriodException;
use App\Http\Controllers\Controller;
use App\Models\BillingProduct;
use App\Models\BillingSetting;
use App\Models\InventoryMovement;
use App\Models\ProductType;
use App\Models\PurchaseInvoice;
use App\Models\Warehouse;
use App\Services\Accounting\AccountingEntryService;
use App\Services\Accounting\AccountingPeriodService;
use App\Traits\HasTenantScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InventoryController extends Controller
{
    use HasTenantScope;

    // ── Tipos de producto ───────────────────────────────────────────────────

    public function productTypes()
    {
        $tenantId     = $this->tenantId();
        $productTypes = ProductType::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
            ->withCount('products')->orderBy('name')->get();
        return Inertia::render('Admin/Inventory/ProductTypes', compact('productTypes'));
    }

    public function storeProductType(Request $request)
    {
        $tenantId = $this->tenantId();
        $codeRule = Rule::unique('product_types', 'code')->whereNull('code')->ignore(0);
        if ($request->filled('code')) {
            $codeRule = Rule::unique('product_types', 'code');
            if ($tenantId !== null) $codeRule->where('company_id', $tenantId);
            if ($request->filled('id')) $codeRule->ignore($request->input('id'));
        }

        $data = $request->validate([
            'id'                 => 'nullable|integer|exists:product_types,id',
            'name'               => 'required|string|max:100',
            'code'               => ['nullable', 'string', 'max:20', $codeRule],
            'controls_inventory' => 'boolean',
            'is_active'          => 'boolean',
        ]);

        if (!empty($data['id'])) {
            $productType = ProductType::findOrFail($data['id']);
            if ($tenantId) abort_unless($productType->company_id === $tenantId, 403);
            $productType->update([
                'name'               => $data['name'],
                'code'               => $data['code'] ?? $productType->code,
                'controls_inventory' => $data['controls_inventory'] ?? false,
                'is_active'          => $data['is_active'] ?? true,
            ]);
        } else {
            ProductType::create([
                'company_id'         => $tenantId,
                'name'               => $data['name'],
                'code'               => $data['code'] ?? null,
                'controls_inventory' => $data['controls_inventory'] ?? false,
                'is_active'          => $data['is_active'] ?? true,
            ]);
        }

        return back()->with('status', 'Tipo de producto guardado.');
    }

    public function destroyProductType(ProductType $productType)
    {
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($productType->company_id === $tenantId, 403);

        if ($productType->products()->exists()) {
            return back()->withErrors('No se puede eliminar: hay productos asignados a este tipo.');
        }
        $productType->delete();
        return back()->with('status', 'Tipo de producto eliminado.');
    }

    // ── Bodegas ─────────────────────────────────────────────────────────────

    public function warehouses()
    {
        $tenantId   = $this->tenantId();
        $warehouses = Warehouse::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
            ->withCount('movements')->orderBy('name')->get();
        return Inertia::render('Admin/Inventory/Warehouses', compact('warehouses'));
    }

    public function storeWarehouse(Request $request)
    {
        $tenantId = $this->tenantId();
        $data     = $request->validate([
            'id'           => 'nullable|integer|exists:warehouses,id',
            'name'         => 'required|string|max:100',
            'code'         => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:500',
            'phone'        => 'nullable|string|max:30',
            'contact_name' => 'nullable|string|max:100',
            'is_active'    => 'boolean',
        ]);

        if (!empty($data['id'])) {
            $warehouse = Warehouse::findOrFail($data['id']);
            if ($tenantId) abort_unless($warehouse->company_id === $tenantId, 403);
            $warehouse->update([
                'name'         => $data['name'],
                'code'         => $data['code'] ?? $warehouse->code,
                'address'      => $data['address'] ?? null,
                'phone'        => $data['phone'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'is_active'    => $data['is_active'] ?? true,
            ]);
        } else {
            Warehouse::create([
                'company_id'   => $tenantId,
                'name'         => $data['name'],
                'code'         => $data['code'] ?? null,
                'address'      => $data['address'] ?? null,
                'phone'        => $data['phone'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
                'is_active'    => $data['is_active'] ?? true,
            ]);
        }

        return back()->with('status', 'Bodega guardada.');
    }

    public function destroyWarehouse(Warehouse $warehouse)
    {
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($warehouse->company_id === $tenantId, 403);

        if ($warehouse->movements()->exists()) {
            return back()->withErrors('No se puede eliminar: hay movimientos registrados en esta bodega.');
        }
        $warehouse->delete();
        return back()->with('status', 'Bodega eliminada.');
    }

    // ── Parámetros ──────────────────────────────────────────────────────────

    public function parameters()
    {
        $tenantId         = $this->tenantId();
        $warehouses       = Warehouse::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))->orderBy('name')->get();
        $productTypes     = ProductType::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))->orderBy('name')->get();
        $salesWarehouseId = (int) BillingSetting::get('inventory_sales_warehouse_id', null, $tenantId);
        return Inertia::render('Admin/Inventory/Parameters', compact('warehouses', 'productTypes', 'salesWarehouseId'));
    }

    public function saveParameters(Request $request)
    {
        $tenantId      = $this->tenantId();
        $warehouseRule = Rule::exists('warehouses', 'id');
        if ($tenantId !== null) $warehouseRule->where('company_id', $tenantId);

        $data = $request->validate([
            'sales_warehouse_id' => ['required', $warehouseRule],
        ]);

        BillingSetting::put('inventory_sales_warehouse_id', $data['sales_warehouse_id'], $tenantId);

        return back()->with('status', 'Bodega de ventas actualizada.');
    }

    // ── Movimientos ─────────────────────────────────────────────────────────

    public function movements(Request $request)
    {
        $tenantId    = $this->tenantId();
        $search      = trim((string) $request->query('search', ''));
        $type        = $request->query('type', '');
        $warehouseId = $request->query('warehouse_id', '');

        $movements = InventoryMovement::with(['product.productType', 'warehouse', 'billingInvoice', 'purchaseInvoice.supplier'])
            ->when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($warehouseId !== '', fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($search !== '', fn ($q) => $q->whereHas('product', fn ($q) => $q
                ->where('description', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
            ))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $warehouses = Warehouse::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
            ->orderBy('name')->get();

        $approvedPurchases = PurchaseInvoice::with('supplier')
            ->when($tenantId !== null, fn ($q) => $q->where('company_id', $tenantId))
            ->where('status', 'approved')
            ->latest('purchase_date')
            ->limit(200)
            ->get();

        $inventoryProducts = BillingProduct::with('productType')
            ->when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
            ->whereHas('productType', fn ($q) => $q->where('controls_inventory', true))
            ->orderBy('description')
            ->get();

        return Inertia::render('Admin/Inventory/Movements', compact(
            'movements', 'warehouses', 'approvedPurchases',
            'inventoryProducts', 'search', 'type', 'warehouseId'
        ));
    }

    public function storeMovement(Request $request)
    {
        $tenantId = $this->tenantId();

        $productRule  = Rule::exists('billing_products', 'id');
        $warehouseRule = Rule::exists('warehouses', 'id');
        $purchaseRule  = Rule::exists('purchase_invoices', 'id');
        if ($tenantId !== null) {
            $productRule->where('company_id', $tenantId);
            $warehouseRule->where('company_id', $tenantId);
            $purchaseRule->where('company_id', $tenantId);
        }

        $data = $request->validate([
            'product_id'          => ['required', $productRule],
            'warehouse_id'        => ['required', $warehouseRule],
            'type'                => 'required|in:entry,exit',
            'quantity'            => 'required|numeric|min:0.01',
            'document_type'       => 'required|in:manual,purchase',
            'document_number'     => 'nullable|string|max:255',
            'purchase_invoice_id' => ['nullable', $purchaseRule],
            'notes'               => 'nullable|string|max:1000',
        ]);

        $product = BillingProduct::with('productType')->findOrFail($data['product_id']);

        if (!$product->productType?->controls_inventory) {
            return back()->withErrors('Este producto no controla existencias.');
        }

        try {
            app(AccountingPeriodService::class)->validateDateOrFail(now(), $tenantId);
        } catch (AccountingPeriodException $e) {
            return back()->withErrors($e->getMessage());
        }

        $purchaseInvoiceId = null;
        $docNumber         = $data['document_number'] ?? null;

        if ($data['type'] === 'entry' && $data['document_type'] === 'purchase') {
            if (empty($data['purchase_invoice_id'])) {
                return back()->withErrors('Debe seleccionar una factura de compra aprobada.');
            }
            $purchase = PurchaseInvoice::findOrFail($data['purchase_invoice_id']);
            if ($purchase->status !== 'approved') {
                return back()->withErrors('Solo se pueden registrar entradas de facturas aprobadas.');
            }
            $purchaseInvoiceId = $purchase->id;
            $docNumber         = $purchase->invoice_number;
        }

        $movement = InventoryMovement::create([
            'company_id'          => $tenantId,
            'product_id'          => $data['product_id'],
            'warehouse_id'        => $data['warehouse_id'],
            'type'                => $data['type'],
            'quantity'            => $data['quantity'],
            'document_type'       => $data['document_type'],
            'document_number'     => $docNumber,
            'billing_invoice_id'  => null,
            'purchase_invoice_id' => $purchaseInvoiceId,
            'notes'               => $data['notes'] ?? null,
        ]);

        $this->syncWarehouseStock($product, (int) $data['warehouse_id'], $data['quantity'], $data['type']);

        try {
            app(AccountingEntryService::class)->createFromInventory($movement->load('product'), $request->user()?->id, $tenantId);
        } catch (\Throwable) {
            // No interrumpir el registro si falla la contabilidad
        }

        return back()->with('status', 'Movimiento de inventario registrado.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public static function syncWarehouseStock(BillingProduct $product, int $warehouseId, float $qty, string $type): void
    {
        $existing = $product->warehouses()->where('warehouse_id', $warehouseId)->first();

        if ($existing) {
            $current  = (int) $existing->pivot->stock_quantity;
            $newStock = $type === 'entry' ? $current + $qty : max(0, $current - $qty);
            $product->warehouses()->updateExistingPivot($warehouseId, ['stock_quantity' => $newStock]);
        } else {
            $initial = $type === 'entry' ? (int) $qty : 0;
            $product->warehouses()->attach($warehouseId, ['stock_quantity' => $initial]);
        }

        $total = \DB::table('billing_product_warehouse')
            ->where('billing_product_id', $product->id)
            ->sum('stock_quantity');
        $product->update(['stock_quantity' => $total]);
    }
}
