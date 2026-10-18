<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends Model
{
    protected $fillable = ['customer_id', 'company_id', 'name', 'code', 'address', 'phone', 'contact_name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

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

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('company_id', $customerId);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
