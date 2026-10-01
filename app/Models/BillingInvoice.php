<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingInvoice extends Model
{
    protected $fillable = [
        'number_control',
        'generation_code',
        'document_type',
        'issued_at',
        'customer_id',
        'customer_name',
        'subtotal',
        'iva',
        'total',
        'status',
        'has_error',
        'accepted',
        'email_sent',
        'reception_stamp',
        'void_stamp',
        'voided_at',
        'void_reason',
        'void_json',
        'observations',
        'json_dte',
        'signed_dte',
        'payment_status',
        'amount_paid',
        'paid_at',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'subtotal' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'has_error' => 'boolean',
        'accepted' => 'boolean',
        'email_sent' => 'boolean',
        'voided_at' => 'datetime',
        'paid_at' => 'datetime',
        'void_json' => 'array',
        'json_dte' => 'array',
        'signed_dte' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(BillingInvoicePayment::class);
    }

    public function getBalanceAttribute(): float
    {
        return max(0, (float) $this->total - (float) $this->amount_paid);
    }
}
