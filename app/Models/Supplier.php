<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'created_by',
        'updated_by',
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
