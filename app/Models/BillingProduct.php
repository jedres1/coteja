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
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'tributes' => 'array',
        'is_exempt' => 'boolean',
    ];

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
