<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PayrollConcept extends Model
{
    protected $fillable = [
        'customer_id', 'company_id', 'name', 'type', 'calculation_method', 'amount', 'rate', 'formula',
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
        return $query->where('company_id', $customerId);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public static function companyHasSystemConcepts(int $companyId): bool
    {
        return static::where('company_id', $companyId)->whereNotNull('system_key')->exists();
    }

    /** @deprecated Use companyHasSystemConcepts */
    public static function customerHasSystemConcepts(int $customerId): bool
    {
        return static::companyHasSystemConcepts($customerId);
    }

    public static function copySystemConceptsFor(int $companyId): void
    {
        $templates = static::whereNull('company_id')->whereNull('customer_id')->whereNotNull('system_key')->get();
        foreach ($templates as $template) {
            static::firstOrCreate(
                ['company_id' => $companyId, 'system_key' => $template->system_key],
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
