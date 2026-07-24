<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'customer_id',
        'business_name',
        'trade_name',
        'nit_dui',
        'nrc',
        'email',
        'phone',
        'environment',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
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
