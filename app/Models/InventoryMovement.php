<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'customer_id', 'company_id', 'product_id', 'warehouse_id', 'type', 'quantity',
        'document_type', 'document_number',
        'billing_invoice_id', 'purchase_invoice_id', 'notes',
    ];

    protected $casts = ['quantity' => 'decimal:2'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(BillingProduct::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function billingInvoice()
    {
        return $this->belongsTo(BillingInvoice::class);
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function journalEntry()
    {
        return $this->hasOne(JournalEntry::class, 'source_id')
            ->where('source_type', 'inventory');
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
