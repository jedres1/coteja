<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['license_id', 'company_id', 'amount', 'iva', 'total', 'period', 'method', 'reference', 'status', 'paid_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'iva' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
