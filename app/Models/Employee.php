<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'customer_id', 'created_by', 'updated_by',
        'code', 'name', 'email', 'dui', 'isss_number', 'nup', 'afp',
        'position', 'department', 'hire_date', 'salary',
        'bank_name', 'bank_account', 'is_active',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'salary'    => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function payrollConcepts(): BelongsToMany
    {
        return $this->belongsToMany(PayrollConcept::class, 'employee_payroll_concept');
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }
}
