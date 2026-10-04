<?php

namespace Database\Seeders;

use App\Models\AccountingAccount;
use App\Models\PayrollConcept;
use Illuminate\Database\Seeder;

class PayrollSystemConceptsSeeder extends Seeder
{
    public function run(): void
    {
        $concepts = [
            [
                'system_key' => 'salario',
                'name' => 'Salario base',
                'type' => 'beneficio',
                'formula' => 'Salario mensual + horas extra + bonos aprobados',
                'account_code' => '4.1.01',
            ],
            [
                'system_key' => 'isss_laboral',
                'name' => 'ISSS laboral',
                'type' => 'deduccion',
                'formula' => 'min(salary, isss_salary_cap) * isss_employee_rate',
                'account_code' => '2.1.05',
            ],
            [
                'system_key' => 'afp_laboral',
                'name' => 'AFP laboral',
                'type' => 'deduccion',
                'formula' => 'salary * afp_employee_rate',
                'account_code' => '2.1.06',
            ],
            [
                'system_key' => 'isr',
                'name' => 'Impuesto sobre la renta (ISR)',
                'type' => 'deduccion',
                'formula' => 'Tabla mensual sobre gross - ISSS laboral - AFP laboral',
                'account_code' => '2.1.11',
            ],
            [
                'system_key' => 'isss_patronal',
                'name' => 'ISSS patronal',
                'type' => 'beneficio',
                'formula' => 'min(salary, isss_salary_cap) * isss_employer_rate',
                'account_code' => '4.1.05',
                'payable_account_code' => '2.1.12',
            ],
            [
                'system_key' => 'afp_patronal',
                'name' => 'AFP patronal',
                'type' => 'beneficio',
                'formula' => 'salary * afp_employer_rate',
                'account_code' => '4.1.06',
                'payable_account_code' => '2.1.13',
            ],
        ];

        foreach ($concepts as $data) {
            $accountCode = $data['account_code'];
            $payableAccountCode = $data['payable_account_code'] ?? null;
            unset($data['account_code'], $data['payable_account_code']);

            $data['account_id'] = AccountingAccount::where('code', $accountCode)->value('id');
            $data['payable_account_id'] = $payableAccountCode
                ? AccountingAccount::where('code', $payableAccountCode)->value('id')
                : null;
            $data['calculation_method'] = 'formula';
            $data['applies_to_all'] = true;
            $data['is_active'] = true;

            if (! $data['account_id'] || ($payableAccountCode && ! $data['payable_account_id'])) {
                throw new \RuntimeException('Falta una cuenta requerida para el concepto legal ' . $data['system_key'] . '.');
            }

            PayrollConcept::firstOrCreate(
                ['system_key' => $data['system_key']],
                $data
            );
        }
    }
}