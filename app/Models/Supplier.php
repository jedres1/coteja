<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'document_type',
        'document_number',
        'nrc',
        'trade_name',
        'business_activity',
        'activity_description',
        'address_department',
        'address_municipality',
        'address',
        'billing_email',
        'billing_phone',
        'status',
        'notes',
    ];

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class);
    }
}
