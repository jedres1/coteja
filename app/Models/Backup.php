<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $fillable = [
        'license_id',
        'company_id',
        'filename',
        'path',
        'size_bytes',
        'checksum',
        'encryption',
        'compression',
        'device_id',
        'uploaded_at',
    ];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
