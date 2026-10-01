<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingProduct extends Model
{
    protected $fillable = [
        'code',
        'description',
        'type',
        'price',
        'unit',
        'tributes',
        'is_exempt',
        'notes',
        'stock_quantity',
        'min_stock',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'tributes'       => 'array',
        'is_exempt'      => 'boolean',
        'stock_quantity' => 'integer',
        'min_stock'      => 'integer',
    ];

    public function productType()
    {
        return $this->belongsTo(ProductType::class);
    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'billing_product_warehouse')
            ->withPivot('stock_quantity')
            ->withTimestamps();
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class, 'product_id');
    }

    public function toInvoiceItem(): array
    {
        return [
            'codigo' => $this->code,
            'descripcion' => $this->description,
            'tipo_item' => (int) $this->type,
            'precio_unitario' => (float) $this->price,
            'unidad_medida' => $this->unit,
            'exento' => $this->is_exempt,
        ];
    }
}
