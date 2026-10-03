<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'code', 'name', 'dui', 'isss_number', 'nup', 'afp',
        'position', 'department', 'hire_date', 'salary',
        'bank_name', 'bank_account', 'is_active',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'salary'    => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }
}
