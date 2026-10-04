<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\PayrollConcept;
use App\Models\PayrollSetting;

class PayrollCalculatorService
{
    public function __construct(
        private PayrollSetting $settings,
        private PayrollFormulaEvaluator $formulaEvaluator = new PayrollFormulaEvaluator(),
    ) {}

    public function calculateLine(Employee $employee, array $overrides = []): array
    {
        $salary         = (float) $employee->salary;
        $overtimeHours  = (float) ($overrides['overtime_hours'] ?? 0);
        $overtimeAmount = (float) ($overrides['overtime_amount'] ?? 0);
        $bonuses        = (float) ($overrides['bonuses'] ?? 0);
        $baseGross      = round($salary + $overtimeAmount + $bonuses, 2);
        $conceptDetails = [];
        $conceptBenefits = 0.0;
        $conceptDeductions = 0.0;

        $systemConcepts = PayrollConcept::where('is_active', true)
            ->whereNotNull('system_key')
            ->get()
            ->keyBy('system_key');

        $concepts = PayrollConcept::where('is_active', true)
            ->whereNull('system_key')
            ->where(function ($query) use ($employee) {
                $query->where('applies_to_all', true)
                    ->orWhereHas('employees', fn ($employees) => $employees->whereKey($employee->id));
            })
            ->with('account')
            ->get();

        foreach ($concepts as $concept) {
            $input = (float) ($overrides['concept_inputs'][$concept->id] ?? 0);
            $amount = match ($concept->calculation_method) {
                'fijo' => (float) $concept->amount,
                'porcentaje' => round($salary * (float) $concept->rate / 100, 2),
                'formula' => $this->formulaEvaluator->evaluate((string) $concept->formula, [
                    'salary' => $salary,
                    'gross' => $baseGross,
                    'overtime' => $overtimeAmount,
                ]),
                'editable' => $input,
                default => 0.0,
            };
            $amount = round(max(0, $amount), 2);

            if ($concept->type === 'beneficio') {
                $conceptBenefits += $amount;
            } else {
                $conceptDeductions += $amount;
            }

            $conceptDetails[] = [
                'concept_id' => $concept->id,
                'name' => $concept->name,
                'type' => $concept->type,
                'amount' => $amount,
                'account_id' => $concept->account_id,
                'calculation_method' => $concept->calculation_method,
            ];
        }

        $gross = round($baseGross + $conceptBenefits, 2);

        $isssEmployee = $this->calculateIsss($salary, false);
        $afpEmployee  = round($salary * (float) $this->settings->afp_employee_rate, 2);
        $taxableIncome = max(0, round($gross - $isssEmployee - $afpEmployee, 2));
        $isr          = $this->calculateIsr($taxableIncome);
        $totalDed     = round($isssEmployee + $afpEmployee + $isr + $conceptDeductions, 2);
        $netSalary    = round($gross - $totalDed, 2);
        $isssEmployer = $this->calculateIsss($salary, true);
        $afpEmployer  = round($salary * (float) $this->settings->afp_employer_rate, 2);
        $totalCost    = round($gross + $isssEmployer + $afpEmployer, 2);

        $systemDetails = [];
        foreach ([
            'salario' => ['name' => 'Salario base', 'type' => 'beneficio', 'amount' => $baseGross],
            'isss_laboral' => ['name' => 'ISSS laboral', 'type' => 'deduccion', 'amount' => $isssEmployee],
            'isss_patronal' => ['name' => 'ISSS patronal', 'type' => 'beneficio', 'amount' => $isssEmployer],
            'afp_laboral' => ['name' => 'AFP laboral', 'type' => 'deduccion', 'amount' => $afpEmployee],
            'afp_patronal' => ['name' => 'AFP patronal', 'type' => 'beneficio', 'amount' => $afpEmployer],
            'isr' => ['name' => 'Impuesto sobre la renta (ISR)', 'type' => 'deduccion', 'amount' => $isr],
        ] as $systemKey => $values) {
            $concept = $systemConcepts->get($systemKey);
            $systemDetails[] = [
                'concept_id' => $concept?->id,
                'system_key' => $systemKey,
                'name' => $concept?->name ?? $values['name'],
                'type' => $concept?->type ?? $values['type'],
                'amount' => $values['amount'],
                'account_id' => $concept?->account_id,
                'payable_account_id' => $concept?->payable_account_id,
                'calculation_method' => 'legal',
                'formula' => $concept?->formula,
            ];
        }

        $conceptDetails = array_merge($systemDetails, $conceptDetails);

        return compact('salary', 'overtimeHours', 'overtimeAmount', 'bonuses', 'gross', 'conceptDetails',
            'isssEmployee', 'afpEmployee', 'isr', 'totalDed', 'netSalary',
            'isssEmployer', 'afpEmployer', 'totalCost');
    }

    private function calculateIsr(float $taxableIncome): float
    {
        if ($taxableIncome <= 550.00) return 0.0;
        if ($taxableIncome <= 895.24) return round(($taxableIncome - 550.00) * 0.10 + 17.67, 2);
        if ($taxableIncome <= 2038.10) return round(($taxableIncome - 895.24) * 0.20 + 60.00, 2);
        return round(($taxableIncome - 2038.10) * 0.30 + 288.57, 2);
    }

    private function calculateIsss(float $salary, bool $isEmployer): float
    {
        $cap  = (float) $this->settings->isss_salary_cap;
        $base = min($salary, $cap);
        $rate = $isEmployer ? (float) $this->settings->isss_employer_rate : (float) $this->settings->isss_employee_rate;
        return round($base * $rate, 2);
    }
}
