<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PayrollConcept extends Model
{
    protected $fillable = [
        'customer_id', 'name', 'type', 'calculation_method', 'amount', 'rate', 'formula',
        'account_id', 'payable_account_id', 'system_key', 'applies_to_all', 'is_active',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'rate'           => 'decimal:4',
        'applies_to_all' => 'boolean',
        'is_active'      => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

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

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public static function customerHasSystemConcepts(int $customerId): bool
    {
        return static::where('customer_id', $customerId)->whereNotNull('system_key')->exists();
    }

    public static function copySystemConceptsFor(int $customerId): void
    {
        $templates = static::whereNull('customer_id')->whereNotNull('system_key')->get();
        foreach ($templates as $template) {
            static::firstOrCreate(
                ['customer_id' => $customerId, 'system_key' => $template->system_key],
                [
                    'name'               => $template->name,
                    'type'               => $template->type,
                    'calculation_method' => $template->calculation_method,
                    'amount'             => $template->amount,
                    'rate'               => $template->rate,
                    'formula'            => $template->formula,
                    'applies_to_all'     => $template->applies_to_all,
                    'is_active'          => $template->is_active,
                ]
            );
        }
    }
}
