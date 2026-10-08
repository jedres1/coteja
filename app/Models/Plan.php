<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'monthly_price',
        'annual_price',
        'implementation_fee',
        'additional_user_price',
        'included_users',
        'max_devices',
        'support_included',
        'cloud_backup',
        'unlimited_documents',
        'is_active',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'implementation_fee' => 'decimal:2',
        'additional_user_price' => 'decimal:2',
        'support_included' => 'boolean',
        'cloud_backup' => 'boolean',
        'unlimited_documents' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function licenses()
    {
        return $this->hasMany(License::class);
    }
}
