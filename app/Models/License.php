<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $fillable = [
        'customer_id',
        'company_id',
        'plan_id',
        'license_key',
        'status',
        'starts_at',
        'expires_at',
        'max_users',
        'max_devices',
        'grace_days',
        'last_validated_at',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'expires_at' => 'date',
        'last_validated_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function devices()
    {
        return $this->hasMany(LicenseDevice::class);
    }

    public function licenseUsers()
    {
        return $this->hasMany(LicenseUser::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function backups()
    {
        return $this->hasMany(Backup::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && (!$this->expires_at || $this->expires_at->isToday() || $this->expires_at->isFuture());
    }
}
