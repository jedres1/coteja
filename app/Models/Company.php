<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'business_name',
        'trade_name',
        'nit_dui',
        'nrc',
        'business_activity',
        'email',
        'phone',
        'environment',
        'module_accesses',
    ];

    protected function casts(): array
    {
        return [
            'module_accesses' => 'array',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function latestLicense()
    {
        return $this->hasOne(License::class)->latest();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function backups()
    {
        return $this->hasMany(Backup::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
