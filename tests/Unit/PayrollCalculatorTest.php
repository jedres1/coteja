<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\PayrollConcept;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayrollCalculatorService;
use App\Services\Payroll\PayrollEntryService;
use App\Services\Payroll\PayrollFormulaEvaluator;
use Database\Seeders\AccountingAccountSeeder;
use Database\Seeders\PayrollSystemConceptsSeeder;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayrollCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCalculatorService $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $settings = PayrollSetting::create([
            'isss_salary_cap'    => 1000,
            'isss_employee_rate' => 0.0300,
            'isss_employer_rate' => 0.0750,
            'afp_employee_rate'  => 0.0725,
            'afp_employer_rate'  => 0.0875,
        ]);
        $this->calc = new PayrollCalculatorService($settings);
    }

    public function test_salary_below_isr_threshold(): void
    {
        $emp  = new Employee(['salary' => 400]);
        $line = $this->calc->calculateLine($emp);

        $this->assertEquals(0.00, $line['isr']);
        $this->assertEquals(12.00, $line['isssEmployee']);   // 400 × 3%
        $this->assertEquals(29.00, $line['afpEmployee']);    // 400 × 7.25%
        $this->assertEquals(30.00, $line['isssEmployer']);   // 400 × 7.5%
        $this->assertEquals(35.00, $line['afpEmployer']);    // 400 × 8.75%
        $this->assertEquals(359.00, $line['netSalary']);     // 400 - 12 - 29 - 0
        $this->assertEquals(465.00, $line['totalCost']);     // 400 + 30 + 35

        $legalConcepts = array_column($line['conceptDetails'], null, 'system_key');
        $this->assertSame(400.0, $legalConcepts['salario']['amount']);
        $this->assertSame(12.0, $legalConcepts['isss_laboral']['amount']);
        $this->assertSame(30.0, $legalConcepts['isss_patronal']['amount']);
        $this->assertSame(29.0, $legalConcepts['afp_laboral']['amount']);
        $this->assertSame(35.0, $legalConcepts['afp_patronal']['amount']);
        $this->assertSame(0.0, $legalConcepts['isr']['amount']);
    }

    public function test_first_payroll_settings_creation_includes_default_contribution_rates(): void
    {
        PayrollSetting::query()->delete();
        $settings = PayrollSetting::current();

        $line = (new PayrollCalculatorService($settings))->calculateLine(
            new Employee(['salary' => 1100])
        );

        $this->assertSame(30.0, $line['isssEmployee']);
        $this->assertSame(79.75, $line['afpEmployee']);
        $this->assertSame(75.0, $line['isssEmployer']);
        $this->assertSame(96.25, $line['afpEmployer']);
    }

    public function test_system_payroll_concepts_use_catalog_accounts_and_keep_manual_remapping(): void
    {
        (new AccountingAccountSeeder())->run();
        (new PayrollSystemConceptsSeeder())->run();

        $concepts = PayrollConcept::whereNotNull('system_key')
            ->with(['account', 'payableAccount'])
            ->get()
            ->keyBy('system_key');

        $this->assertSame('4.1.01', $concepts['salario']->account->code);
        $this->assertSame('2.1.05', $concepts['isss_laboral']->account->code);
        $this->assertSame('2.1.06', $concepts['afp_laboral']->account->code);
        $this->assertSame('2.1.11', $concepts['isr']->account->code);
        $this->assertSame('4.1.05', $concepts['isss_patronal']->account->code);
        $this->assertSame('2.1.12', $concepts['isss_patronal']->payableAccount->code);
        $this->assertSame('4.1.06', $concepts['afp_patronal']->account->code);
        $this->assertSame('2.1.13', $concepts['afp_patronal']->payableAccount->code);

        $concepts['salario']->update(['account_id' => $concepts['isss_patronal']->account_id]);
        (new PayrollSystemConceptsSeeder())->run();

        $this->assertSame(
            $concepts['isss_patronal']->account_id,
            PayrollConcept::where('system_key', 'salario')->value('account_id')
        );
    }

    public function test_isr_uses_monthly_2025_table_after_employee_contributions(): void
    {
        $line = $this->calc->calculateLine(new Employee(['salary' => 700]));

        $this->assertSame(21.0, $line['isssEmployee']);
        $this->assertSame(50.75, $line['afpEmployee']);
        $this->assertSame(25.50, $line['isr']);
        $this->assertSame(602.75, $line['netSalary']);
    }

    public function test_isr_monthly_table_uses_fixed_fees_at_upper_brackets(): void
    {
        $secondBracket = $this->calc->calculateLine(new Employee(['salary' => 1000]));
        $fourthBracket = $this->calc->calculateLine(new Employee(['salary' => 2500]));

        $this->assertSame(60.45, $secondBracket['isr']);
        $this->assertSame(363.77, $fourthBracket['isr']);
    }

    public function test_isss_cap_at_1000(): void
    {
        $emp  = new Employee(['salary' => 1500]);
        $line = $this->calc->calculateLine($emp);

        // ISSS capped at $1000 base
        $this->assertEquals(30.00, $line['isssEmployee']);   // 1000 × 3% (cap)
        $this->assertEquals(75.00, $line['isssEmployer']);   // 1000 × 7.5% (cap)
        // AFP sin cap
        $this->assertEquals(108.75, $line['afpEmployee']);  // 1500 × 7.25%
        $this->assertEquals(131.25, $line['afpEmployer']);  // 1500 × 8.75%
    }

    public function test_partition_balances(): void
    {
        $emp  = new Employee(['salary' => 800]);
        $line = $this->calc->calculateLine($emp);

        // DEBE = HABER
        $debe  = round($line['gross'] + $line['isssEmployer'] + $line['afpEmployer'], 2);
        $haber = round(
            $line['isssEmployee'] + $line['isssEmployer'] +
            $line['afpEmployee']  + $line['afpEmployer'] +
            $line['isr']          + $line['netSalary'],
            2
        );
        $this->assertEquals($debe, $haber, 'La partida no cuadra');
    }

    public function test_formula_evaluator_supports_variables_and_rejects_code(): void
    {
        $evaluator = new PayrollFormulaEvaluator();

        $this->assertSame(55.0, $evaluator->evaluate('(salary * 0.05) + overtime', [
            'salary' => 1000,
            'overtime' => 5,
            'gross' => 1000,
        ]));
        $this->expectException(\InvalidArgumentException::class);
        $evaluator->evaluate('system(1)', ['salary' => 1000]);
    }

    public function test_employee_concepts_calculate_only_for_assigned_employees(): void
    {
        $account = \App\Models\AccountingAccount::create([
            'code' => '420101',
            'name' => 'Comisiones',
            'type' => 'gasto',
            'nature' => 'deudora',
            'level' => 3,
        ]);
        $seller = Employee::create([
            'code' => 'VEND-01', 'name' => 'Vendedor', 'hire_date' => '2026-01-01', 'salary' => 1000,
        ]);
        $accountant = Employee::create([
            'code' => 'CONT-01', 'name' => 'Contador', 'hire_date' => '2026-01-01', 'salary' => 1000,
        ]);
        $commission = PayrollConcept::create([
            'name' => 'Comisión',
            'type' => 'beneficio',
            'calculation_method' => 'formula',
            'formula' => 'salary * 0.05',
            'account_id' => $account->id,
        ]);
        $commission->employees()->attach($seller);
        $editableBonus = PayrollConcept::create([
            'name' => 'Bono editable',
            'type' => 'beneficio',
            'calculation_method' => 'editable',
            'account_id' => $account->id,
        ]);
        $editableBonus->employees()->attach($seller);

        $sellerLine = $this->calc->calculateLine($seller, [
            'concept_inputs' => [$editableBonus->id => 75],
        ]);
        $accountantLine = $this->calc->calculateLine($accountant);

        $this->assertSame(1125.0, $sellerLine['gross']);
        $customDetails = array_values(array_filter(
            $sellerLine['conceptDetails'],
            fn ($concept) => ! isset($concept['system_key'])
        ));
        $this->assertSame(2, count($customDetails));
        $this->assertSame(75.0, $customDetails[1]['amount']);
        $this->assertSame(1000.0, $accountantLine['gross']);
        $this->assertSame([], array_filter(
            $accountantLine['conceptDetails'],
            fn ($concept) => ! isset($concept['system_key'])
        ));
    }

    public function test_concept_can_apply_to_all_employees(): void
    {
        $account = \App\Models\AccountingAccount::create([
            'code' => '420102',
            'name' => 'Bonificaciones',
            'type' => 'gasto',
            'nature' => 'deudora',
            'level' => 3,
        ]);
        $employee = Employee::create([
            'code' => 'EMP-ALL', 'name' => 'Empleado', 'hire_date' => '2026-01-01', 'salary' => 500,
        ]);
        PayrollConcept::create([
            'name' => 'Bono fijo',
            'type' => 'beneficio',
            'calculation_method' => 'fijo',
            'amount' => 25,
            'account_id' => $account->id,
            'applies_to_all' => true,
        ]);

        $line = $this->calc->calculateLine($employee);

        $this->assertSame(525.0, $line['gross']);
        $bonus = collect($line['conceptDetails'])->firstWhere('name', 'Bono fijo');
        $this->assertSame(25.0, $bonus['amount']);
    }

    public function test_payroll_entry_accumulates_concepts_by_account_and_balances(): void
    {
        (new AccountingAccountSeeder())->run();
        (new PayrollSystemConceptsSeeder())->run();
        $salaryPayableAccount = AccountingAccount::where('code', '2.1.07')->firstOrFail();
        $benefitAccount = AccountingAccount::where('code', '4.1.02')->firstOrFail();
        $payableAccount = AccountingAccount::where('code', '2.1.10')->firstOrFail();
        PayrollSetting::first()->update([
            'account_salaries_payable_id' => $salaryPayableAccount->id,
        ]);
        $legal = PayrollConcept::whereNotNull('system_key')->get()->keyBy('system_key');
        AccountingPackage::where('code', 'NOM')->update(['is_active' => true]);

        $period = PayrollPeriod::create([
            'name' => 'Nómina de prueba',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'status' => 'borrador',
        ]);

        foreach ([['VEND-11', 100, 20], ['VEND-12', 50, 10]] as [$code, $benefit, $deduction]) {
            $employee = Employee::create([
                'code' => $code, 'name' => $code, 'hire_date' => '2026-01-01', 'salary' => 1000,
            ]);
            PayrollLine::create([
                'payroll_period_id' => $period->id,
                'employee_id' => $employee->id,
                'salary' => 1000,
                'overtime_hours' => 0,
                'overtime_amount' => 0,
                'bonuses' => 0,
                'gross_salary' => 1000 + $benefit,
                'isss_employee' => 30,
                'afp_employee' => 72.5,
                'isr' => 0,
                'total_deductions' => 102.5 + $deduction,
                'net_salary' => 1000 + $benefit - 102.5 - $deduction,
                'isss_employer' => 75,
                'afp_employer' => 87.5,
                'total_employer_cost' => 1000 + $benefit + 162.5,
                'concept_details' => [
                    ['system_key' => 'salario', 'name' => 'Salario base', 'type' => 'beneficio', 'amount' => 1000, 'account_id' => $legal['salario']->account_id],
                    ['system_key' => 'isss_laboral', 'name' => 'ISSS laboral', 'type' => 'deduccion', 'amount' => 30, 'account_id' => $legal['isss_laboral']->account_id],
                    ['system_key' => 'afp_laboral', 'name' => 'AFP laboral', 'type' => 'deduccion', 'amount' => 72.5, 'account_id' => $legal['afp_laboral']->account_id],
                    ['system_key' => 'isr', 'name' => 'ISR', 'type' => 'deduccion', 'amount' => 0, 'account_id' => $legal['isr']->account_id],
                    ['system_key' => 'isss_patronal', 'name' => 'ISSS patronal', 'type' => 'beneficio', 'amount' => 75, 'account_id' => $legal['isss_patronal']->account_id, 'payable_account_id' => $legal['isss_patronal']->payable_account_id],
                    ['system_key' => 'afp_patronal', 'name' => 'AFP patronal', 'type' => 'beneficio', 'amount' => 87.5, 'account_id' => $legal['afp_patronal']->account_id, 'payable_account_id' => $legal['afp_patronal']->payable_account_id],
                    ['name' => 'Comisión', 'type' => 'beneficio', 'amount' => $benefit, 'account_id' => $benefitAccount->id],
                    ['name' => 'Anticipo', 'type' => 'deduccion', 'amount' => $deduction, 'account_id' => $payableAccount->id],
                ],
            ]);
        }

        $entry = (new PayrollEntryService())->createFromPayroll($period);
        $benefitLines = $entry->lines()->where('description', 'like', 'Beneficios de nómina%')->get();
        $deductionLines = $entry->lines()->where('description', 'like', 'Deducciones de nómina%')->get();

        $this->assertCount(1, $benefitLines);
        $this->assertSame(150.0, (float) $benefitLines->first()->debit);
        $this->assertCount(1, $deductionLines);
        $this->assertSame(30.0, (float) $deductionLines->first()->credit);
        $this->assertSame($entry->total_debit, $entry->total_credit);
    }
}
