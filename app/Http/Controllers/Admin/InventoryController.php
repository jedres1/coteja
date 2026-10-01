<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingProduct;
use App\Models\BillingSetting;
use App\Models\InventoryMovement;
use App\Models\ProductType;
use App\Models\PurchaseInvoice;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    // ── Tipos de producto ───────────────────────────────────────────────────

    public function productTypes()
    {
        $types = ProductType::withCount('products')->orderBy('name')->get();
        return view('admin.inventory.product-types', compact('types'));
    }

    public function storeProductType(Request $request)
    {
        $data = $request->validate([
            'id'                 => 'nullable|integer|exists:product_types,id',
            'name'               => 'required|string|max:100',
            'controls_inventory' => 'boolean',
        ]);

        ProductType::updateOrCreate(
            ['id' => $data['id'] ?? null],
            ['name' => $data['name'], 'controls_inventory' => $data['controls_inventory'] ?? false],
        );

        return back()->with('status', 'Tipo de producto guardado.');
    }

    public function destroyProductType(ProductType $productType)
    {
        if ($productType->products()->exists()) {
            return back()->withErrors('No se puede eliminar: hay productos asignados a este tipo.');
        }
        $productType->delete();
        return back()->with('status', 'Tipo de producto eliminado.');
    }

    // ── Bodegas ─────────────────────────────────────────────────────────────

    public function warehouses()
    {
        $warehouses = Warehouse::withCount('movements')->orderBy('name')->get();
        return view('admin.inventory.warehouses', compact('warehouses'));
    }

    public function storeWarehouse(Request $request)
    {
        $data = $request->validate([
            'id'      => 'nullable|integer|exists:warehouses,id',
            'name'    => 'required|string|max:100',
            'address' => 'required|string|max:500',
            'phone'   => 'nullable|string|max:30',
        ]);

        Warehouse::updateOrCreate(
            ['id' => $data['id'] ?? null],
            ['name' => $data['name'], 'address' => $data['address'], 'phone' => $data['phone'] ?? null],
        );

        return back()->with('status', 'Bodega guardada.');
    }

    // ── Parámetros ──────────────────────────────────────────────────────────

    public function parameters()
    {
        $warehouses      = Warehouse::orderBy('name')->get();
        $salesWarehouseId = (int) BillingSetting::get('inventory_sales_warehouse_id');
        return view('admin.inventory.parameters', compact('warehouses', 'salesWarehouseId'));
    }

    public function saveParameters(Request $request)
    {
        $data = $request->validate([
            'sales_warehouse_id' => 'required|exists:warehouses,id',
        ]);

        BillingSetting::put('inventory_sales_warehouse_id', $data['sales_warehouse_id']);

        return back()->with('status', 'Bodega de ventas actualizada.');
    }

    // ── Movimientos ─────────────────────────────────────────────────────────

    public function movements(Request $request)
    {
        $search      = trim((string) $request->query('search', ''));
        $type        = $request->query('type', '');
        $warehouseId = $request->query('warehouse_id', '');

        $movements = InventoryMovement::with(['product.productType', 'warehouse', 'billingInvoice', 'purchaseInvoice.supplier'])
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($warehouseId !== '', fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($search !== '', fn ($q) => $q->whereHas('product', fn ($q) => $q
                ->where('description', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
            ))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $warehouses = Warehouse::orderBy('name')->get();

        $approvedPurchases = PurchaseInvoice::with('supplier')
            ->where('status', 'approved')
            ->latest('purchase_date')
            ->limit(200)
            ->get();

        $inventoryProducts = BillingProduct::with('productType')
            ->whereHas('productType', fn ($q) => $q->where('controls_inventory', true))
            ->orderBy('description')
            ->get();

        return view('admin.inventory.movements', compact(
            'movements', 'warehouses', 'approvedPurchases',
            'inventoryProducts', 'search', 'type', 'warehouseId'
        ));
    }

    public function storeMovement(Request $request)
    {
        $data = $request->validate([
            'product_id'          => 'required|exists:billing_products,id',
            'warehouse_id'        => 'required|exists:warehouses,id',
            'type'                => 'required|in:entry,exit',
            'quantity'            => 'required|numeric|min:0.01',
            'document_type'       => 'required|in:manual,purchase',
            'document_number'     => 'nullable|string|max:255',
            'purchase_invoice_id' => 'nullable|exists:purchase_invoices,id',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $product = BillingProduct::with('productType')->findOrFail($data['product_id']);

        if (!$product->productType?->controls_inventory) {
            return back()->withErrors('Este producto no controla existencias.');
        }

        $purchaseInvoiceId = null;
        $docNumber = $data['document_number'] ?? null;

        if ($data['type'] === 'entry' && $data['document_type'] === 'purchase') {
            if (empty($data['purchase_invoice_id'])) {
                return back()->withErrors('Debe seleccionar una factura de compra aprobada.');
            }
            $purchase = PurchaseInvoice::findOrFail($data['purchase_invoice_id']);
            if ($purchase->status !== 'approved') {
                return back()->withErrors('Solo se pueden registrar entradas de facturas aprobadas.');
            }
            $purchaseInvoiceId = $purchase->id;
            $docNumber = $purchase->invoice_number;
        }

        $movement = InventoryMovement::create([
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

        // Sync global stock_quantity
        $product->warehouses()->flushCache ?? null;
        $total = \DB::table('billing_product_warehouse')
            ->where('billing_product_id', $product->id)
            ->sum('stock_quantity');
        $product->update(['stock_quantity' => $total]);
    }
}
