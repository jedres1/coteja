<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayrollCalculatorService;
use App\Services\Payroll\PayrollEntryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function index()
    {
        return view('admin.payroll.index', [
            'employees' => Employee::orderBy('name')->get(),
            'periods'   => PayrollPeriod::withCount('lines')
                ->withSum('lines', 'net_salary')
                ->orderBy('period_start', 'desc')
                ->limit(12)
                ->get(),
            'settings'  => PayrollSetting::current()->load([
                'accountingPackage',
                'accountSalaries',
                'accountIsssEmployer',
                'accountAfpEmployer',
                'accountIsssEmployeePayable',
                'accountIsssEmployerPayable',
                'accountAfpEmployeePayable',
                'accountAfpEmployerPayable',
                'accountIsrPayable',
                'accountSalariesPayable',
            ]),
            'accounts'  => AccountingAccount::where('level', 3)->orderBy('code')->get(),
            'packages'  => AccountingPackage::orderBy('code')->get(),
        ]);
    }

    public function storeEmployee(Request $request)
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['required', 'string', 'max:20', 'unique:employees,code'],
            'hire_date' => ['required', 'date'],
            'salary'    => ['required', 'numeric', 'min:0'],
        ]);

        Employee::create($request->only([
            'code', 'name', 'dui', 'isss_number', 'nup', 'afp',
            'position', 'department', 'hire_date', 'salary',
            'bank_name', 'bank_account', 'is_active',
        ]));

        return back()->with('status', 'Empleado creado.');
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['required', 'string', 'max:20', 'unique:employees,code,' . $employee->id],
            'hire_date' => ['required', 'date'],
            'salary'    => ['required', 'numeric', 'min:0'],
        ]);

        $employee->update($request->only([
            'code', 'name', 'dui', 'isss_number', 'nup', 'afp',
            'position', 'department', 'hire_date', 'salary',
            'bank_name', 'bank_account', 'is_active',
        ]));

        return back()->with('status', 'Empleado actualizado.');
    }

    public function destroyEmployee(Employee $employee)
    {
        if ($employee->lines()->exists()) {
            $employee->update(['is_active' => false]);
            return back()->with('status', 'Empleado desactivado (tiene nóminas registradas).');
        }

        $employee->delete();
        return back()->with('status', 'Empleado eliminado.');
    }

    public function storePeriod(Request $request)
    {
        $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $period = PayrollPeriod::create([
            'name'         => $request->name,
            'period_start' => $request->period_start,
            'period_end'   => $request->period_end,
            'notes'        => $request->notes,
            'status'       => 'borrador',
        ]);

        $settings   = PayrollSetting::current();
        $calculator = new PayrollCalculatorService($settings);
        $employees  = Employee::where('is_active', true)->get();

        foreach ($employees as $employee) {
            $calc = $calculator->calculateLine($employee);
            PayrollLine::create([
                'payroll_period_id'   => $period->id,
                'employee_id'         => $employee->id,
                'salary'              => $calc['salary'],
                'overtime_hours'      => $calc['overtimeHours'],
                'overtime_amount'     => $calc['overtimeAmount'],
                'bonuses'             => $calc['bonuses'],
                'gross_salary'        => $calc['gross'],
                'isss_employee'       => $calc['isssEmployee'],
                'afp_employee'        => $calc['afpEmployee'],
                'isr'                 => $calc['isr'],
                'total_deductions'    => $calc['totalDed'],
                'net_salary'          => $calc['netSalary'],
                'isss_employer'       => $calc['isssEmployer'],
                'afp_employer'        => $calc['afpEmployer'],
                'total_employer_cost' => $calc['totalCost'],
            ]);
        }

        return redirect()->route('admin.payroll.periods.show', $period);
    }

    public function showPeriod(PayrollPeriod $period)
    {
        $period->load(['lines.employee', 'journalEntry']);

        return view('admin.payroll.period', [
            'period'   => $period,
            'settings' => PayrollSetting::current(),
        ]);
    }

    public function updateLine(Request $request, PayrollLine $line)
    {
        $request->validate([
            'overtime_hours'  => ['nullable', 'numeric', 'min:0'],
            'overtime_amount' => ['nullable', 'numeric', 'min:0'],
            'bonuses'         => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string'],
        ]);

        $settings   = PayrollSetting::current();
        $calculator = new PayrollCalculatorService($settings);

        $calc = $calculator->calculateLine($line->employee, [
            'overtime_hours'  => $request->overtime_hours ?? 0,
            'overtime_amount' => $request->overtime_amount ?? 0,
            'bonuses'         => $request->bonuses ?? 0,
        ]);

        $line->update([
            'salary'              => $calc['salary'],
            'overtime_hours'      => $calc['overtimeHours'],
            'overtime_amount'     => $calc['overtimeAmount'],
            'bonuses'             => $calc['bonuses'],
            'gross_salary'        => $calc['gross'],
            'isss_employee'       => $calc['isssEmployee'],
            'afp_employee'        => $calc['afpEmployee'],
            'isr'                 => $calc['isr'],
            'total_deductions'    => $calc['totalDed'],
            'net_salary'          => $calc['netSalary'],
            'isss_employer'       => $calc['isssEmployer'],
            'afp_employer'        => $calc['afpEmployer'],
            'total_employer_cost' => $calc['totalCost'],
            'notes'               => $request->notes,
        ]);

        return back()->with('status', 'Línea actualizada.');
    }

    public function applyPeriod(Request $request, PayrollPeriod $period)
    {
        try {
            $service = new PayrollEntryService();
            $entry   = $service->createFromPayroll($period, auth()->id());

            if (! $entry) {
                return back()->withErrors('No se pudo aplicar la nómina. Verifique que el período tenga líneas y no esté ya aplicado.');
            }
        } catch (\RuntimeException $e) {
            return back()->withErrors($e->getMessage());
        }

        return back()->with('status', 'Nómina aplicada. Partida: ' . $entry->entry_number);
    }

    public function destroyPeriod(PayrollPeriod $period)
    {
        if ($period->status !== 'borrador') {
            return back()->withErrors('Solo se pueden eliminar períodos en estado borrador.');
        }

        $period->delete();

        return redirect()->route('admin.payroll.index')->with('status', 'Período eliminado.');
    }

    public function saveSettings(Request $request)
    {
        $data = $request->only([
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
        ]);

        // Nullify blank FK fields
        foreach ([
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
        ] as $field) {
            if (empty($data[$field])) {
                $data[$field] = null;
            }
        }

        PayrollSetting::current()->update($data);

        return back()->with('status', 'Configuración guardada.');
    }
}
