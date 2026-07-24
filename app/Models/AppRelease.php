<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppRelease extends Model
{
    protected $fillable = ['platform', 'version', 'filename', 'download_url', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
