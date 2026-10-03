<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollSetting;

class PayrollCalculatorService
{
    public function __construct(private PayrollSetting $settings) {}

    public function calculateLine(Employee $employee, array $overrides = []): array
    {
        $salary         = (float) $employee->salary;
        $overtimeHours  = (float) ($overrides['overtime_hours'] ?? 0);
        $overtimeAmount = (float) ($overrides['overtime_amount'] ?? 0);
        $bonuses        = (float) ($overrides['bonuses'] ?? 0);
        $gross          = round($salary + $overtimeAmount + $bonuses, 2);

        $isssEmployee = $this->calculateIsss($salary, false);
        $afpEmployee  = round($salary * (float) $this->settings->afp_employee_rate, 2);
        $isr          = $this->calculateIsr($gross);
        $totalDed     = round($isssEmployee + $afpEmployee + $isr, 2);
        $netSalary    = round($gross - $totalDed, 2);
        $isssEmployer = $this->calculateIsss($salary, true);
        $afpEmployer  = round($salary * (float) $this->settings->afp_employer_rate, 2);
        $totalCost    = round($gross + $isssEmployer + $afpEmployer, 2);

        return compact('salary', 'overtimeHours', 'overtimeAmount', 'bonuses', 'gross',
            'isssEmployee', 'afpEmployee', 'isr', 'totalDed', 'netSalary',
            'isssEmployer', 'afpEmployer', 'totalCost');
    }

    private function calculateIsr(float $salary): float
    {
        if ($salary <= 487.18) return 0.0;
        if ($salary <= 912.94) return round($salary * 0.10 - 48.72, 2);
        if ($salary <= 1833.14) return round($salary * 0.20 - 139.97, 2);
        return round($salary * 0.30 - 323.17, 2);
    }

    private function calculateIsss(float $salary, bool $isEmployer): float
    {
        $cap  = (float) $this->settings->isss_salary_cap;
        $base = min($salary, $cap);
        $rate = $isEmployer ? (float) $this->settings->isss_employer_rate : (float) $this->settings->isss_employee_rate;
        return round($base * $rate, 2);
    }
}
