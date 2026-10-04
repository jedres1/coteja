<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    protected $fillable = [
        'payroll_period_id', 'employee_id', 'salary',
        'overtime_hours', 'overtime_amount', 'bonuses',
        'gross_salary', 'isss_employee', 'afp_employee', 'isr',
        'total_deductions', 'net_salary',
        'isss_employer', 'afp_employer', 'total_employer_cost',
        'concept_details', 'notes',
    ];

    protected $casts = [
        'salary'            => 'decimal:2',
        'overtime_hours'    => 'decimal:2',
        'overtime_amount'   => 'decimal:2',
        'bonuses'           => 'decimal:2',
        'gross_salary'      => 'decimal:2',
        'isss_employee'     => 'decimal:2',
        'afp_employee'      => 'decimal:2',
        'isr'               => 'decimal:2',
        'total_deductions'  => 'decimal:2',
        'net_salary'        => 'decimal:2',
        'isss_employer'     => 'decimal:2',
        'afp_employer'      => 'decimal:2',
        'total_employer_cost' => 'decimal:2',
        'concept_details' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
