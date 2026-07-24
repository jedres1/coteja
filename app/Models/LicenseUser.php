<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseUser extends Model
{
    protected $fillable = ['license_id', 'name', 'email', 'role', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}
