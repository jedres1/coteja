<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    protected $fillable = ['name', 'controls_inventory'];

    protected $casts = ['controls_inventory' => 'boolean'];

    public function products()
    {
        return $this->hasMany(BillingProduct::class);
    }
}
