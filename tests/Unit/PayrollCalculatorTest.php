<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayrollCalculatorService;
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
    }

    public function test_salary_in_second_isr_bracket(): void
    {
        $emp  = new Employee(['salary' => 600]);
        $line = $this->calc->calculateLine($emp);

        // ISR = (600 × 0.10) - 48.72 = 11.28
        $this->assertEquals(11.28, $line['isr']);
        $this->assertEquals(18.00, $line['isssEmployee']);   // 600 × 3%
        $this->assertEquals(43.50, $line['afpEmployee']);    // 600 × 7.25%
        $this->assertEquals(527.22, $line['netSalary']);     // 600 - 18 - 43.5 - 11.28
        $this->assertEquals(45.00, $line['isssEmployer']);   // 600 × 7.5%
        $this->assertEquals(52.50, $line['afpEmployer']);    // 600 × 8.75%
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
}
