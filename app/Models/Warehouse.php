<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['name', 'address', 'phone'];

    public function products()
    {
        return $this->belongsToMany(BillingProduct::class, 'billing_product_warehouse')
            ->withPivot('stock_quantity')
            ->withTimestamps();
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
