<?php

namespace App\Models;

use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'plan_id',
        'license_key',
        'license_type',
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
        if ($this->status !== 'active') return false;
        if ($this->license_type === 'propietario') return true;
        return !$this->expires_at || $this->expires_at->isToday() || $this->expires_at->isFuture();
    }
}
