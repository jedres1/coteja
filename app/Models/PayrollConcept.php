<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PayrollConcept extends Model
{
    protected $fillable = [
        'name', 'type', 'calculation_method', 'amount', 'rate', 'formula',
        'account_id', 'payable_account_id', 'system_key', 'applies_to_all', 'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'applies_to_all' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_id');
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'payable_account_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_payroll_concept');
    }
}
