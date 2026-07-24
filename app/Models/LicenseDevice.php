<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseDevice extends Model
{
    protected $fillable = ['license_id', 'device_id', 'device_name', 'is_active', 'activated_at', 'last_seen_at'];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}
