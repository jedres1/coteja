<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSetting extends Model
{
    protected $fillable = [
        'accounting_package_id',
        'account_salaries_id',
        'account_isss_employer_id',
        'account_afp_employer_id',
        'account_isss_employee_payable_id',
        'account_isss_employer_payable_id',
        'account_afp_employee_payable_id',
        'account_afp_employer_payable_id',
        'account_isr_payable_id',
        'account_salaries_payable_id',
        'isss_salary_cap',
        'isss_employee_rate',
        'isss_employer_rate',
        'afp_employee_rate',
        'afp_employer_rate',
    ];

    protected $casts = [
        'isss_salary_cap'    => 'decimal:2',
        'isss_employee_rate' => 'decimal:4',
        'isss_employer_rate' => 'decimal:4',
        'afp_employee_rate'  => 'decimal:4',
        'afp_employer_rate'  => 'decimal:4',
    ];

    public static function current(): static
    {
        return static::firstOrCreate([]);
    }

    public function accountingPackage(): BelongsTo
    {
        return $this->belongsTo(AccountingPackage::class);
    }

    public function accountSalaries(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_salaries_id');
    }

    public function accountIsssEmployer(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_isss_employer_id');
    }

    public function accountAfpEmployer(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_afp_employer_id');
    }

    public function accountIsssEmployeePayable(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_isss_employee_payable_id');
    }

    public function accountIsssEmployerPayable(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_isss_employer_payable_id');
    }

    public function accountAfpEmployeePayable(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_afp_employee_payable_id');
    }

    public function accountAfpEmployerPayable(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_afp_employer_payable_id');
    }

    public function accountIsrPayable(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_isr_payable_id');
    }

    public function accountSalariesPayable(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'account_salaries_payable_id');
    }
}
