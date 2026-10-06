<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use App\Traits\ExportsCsv;
use App\Models\AccountingPackage;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollLine;
use App\Models\PayrollConcept;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayrollCalculatorService;
use App\Services\Payroll\PayrollEntryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PayrollController extends Controller
{
    use ExportsCsv;

    public function index()
    {
        return Inertia::render('Admin/Payroll/Index', [
            'employees' => Employee::orderBy('name')->get(),
            'activeTab' => request()->query('tab') === 'nominas' ? 'nominas' : 'empleados',
            'periods'   => PayrollPeriod::withCount('lines')
                ->withSum('lines', 'net_salary')
                ->orderBy('period_start', 'desc')
                ->limit(12)
                ->get(),
        ]);
    }

    public function conceptsIndex()
    {
        return Inertia::render('Admin/Payroll/Concepts', [
            'employees' => Employee::orderBy('name')->get(),
            'concepts' => PayrollConcept::whereNull('system_key')->with(['account', 'employees'])->orderBy('name')->get(),
            'legalConcepts' => PayrollConcept::whereNotNull('system_key')->with(['account', 'payableAccount'])->orderBy('id')->get(),
            'accounts' => AccountingAccount::where('level', 3)->orderBy('code')->get(),
        ]);
    }

    public function settingsIndex()
    {
        return Inertia::render('Admin/Payroll/Settings', [
            'settings' => $this->payrollSettings(),
            'accounts' => AccountingAccount::where('level', 3)->orderBy('code')->get(),
        ]);
    }

    private function payrollSettings(): PayrollSetting
    {
        return PayrollSetting::current();
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
                'concept_details'     => $calc['conceptDetails'],
            ]);
        }

        return redirect()->route('admin.payroll.periods.show', $period);
    }

    public function showPeriod(PayrollPeriod $period)
    {
        $period->load(['lines.employee', 'journalEntry']);

        return Inertia::render('Admin/Payroll/Period', [
            'period'   => $period,
            'settings' => PayrollSetting::current(),
        ]);
    }

    public function updateLine(Request $request, PayrollLine $line)
    {
        if ($line->period->isApplied()) {
            return back()->withErrors('No se pueden editar líneas de una nómina aplicada.');
        }

        $request->validate([
            'overtime_hours'  => ['nullable', 'numeric', 'min:0'],
            'overtime_amount' => ['nullable', 'numeric', 'min:0'],
            'bonuses'         => ['nullable', 'numeric', 'min:0'],
            'concept_inputs' => ['nullable', 'array'],
            'concept_inputs.*' => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string'],
        ]);

        $settings   = PayrollSetting::current();
        $calculator = new PayrollCalculatorService($settings);

        $calc = $calculator->calculateLine($line->employee, [
            'overtime_hours'  => $request->overtime_hours ?? 0,
            'overtime_amount' => $request->overtime_amount ?? 0,
            'bonuses'         => $request->bonuses ?? 0,
            'concept_inputs'  => $request->input('concept_inputs', []),
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
            'concept_details'     => $calc['conceptDetails'],
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

    public function storeConcept(Request $request)
    {
        $data = $this->validateConcept($request);
        $employeeIds = $data['employee_ids'] ?? [];
        unset($data['employee_ids']);

        $concept = PayrollConcept::create($data);
        if (! $concept->applies_to_all) {
            $concept->employees()->sync($employeeIds);
        }

        return redirect()->route('admin.payroll.concepts.index')->with('status', 'Concepto de nómina creado.');
    }

    public function updateConcept(Request $request, PayrollConcept $concept)
    {
        if ($concept->system_key) {
            return back()->withErrors('La fórmula legal se administra desde los parámetros de nómina; aquí solo se asignan sus cuentas.');
        }

        $data = $this->validateConcept($request);
        $employeeIds = $data['employee_ids'] ?? [];
        unset($data['employee_ids']);

        $concept->update($data);
        $concept->employees()->sync($concept->applies_to_all ? [] : $employeeIds);

        return redirect()->route('admin.payroll.concepts.index')->with('status', 'Concepto de nómina actualizado.');
    }

    public function destroyConcept(PayrollConcept $concept)
    {
        if ($concept->system_key) {
            return back()->withErrors('Los conceptos legales no se pueden eliminar.');
        }

        $concept->delete();

        return redirect()->route('admin.payroll.concepts.index')->with('status', 'Concepto de nómina eliminado.');
    }

    public function updateConceptAccounts(Request $request, PayrollConcept $concept)
    {
        abort_unless($concept->system_key, 404);

        $employerConcept = in_array($concept->system_key, ['isss_patronal', 'afp_patronal'], true);
        $data = $request->validate([
            'account_id' => ['required', 'exists:accounting_accounts,id'],
            'payable_account_id' => [$employerConcept ? 'required' : 'nullable', 'exists:accounting_accounts,id'],
        ]);

        $expectedType = in_array($concept->system_key, ['salario', 'isss_patronal', 'afp_patronal'], true)
            ? 'gasto'
            : 'pasivo';
        $accountValid = AccountingAccount::whereKey($data['account_id'])
            ->where('level', 3)
            ->where('type', $expectedType)
            ->exists();

        if (! $accountValid) {
            return back()->withErrors(['account_id' => 'Seleccione una cuenta de nivel 3 tipo ' . $expectedType . '.']);
        }

        if ($employerConcept && ! AccountingAccount::whereKey($data['payable_account_id'])->where('level', 3)->where('type', 'pasivo')->exists()) {
            return back()->withErrors(['payable_account_id' => 'Seleccione una cuenta de pasivo de nivel 3.']);
        }

        $concept->update([
            'account_id' => $data['account_id'],
            'payable_account_id' => $employerConcept ? $data['payable_account_id'] : null,
        ]);

        return redirect()->route('admin.payroll.concepts.index')->with('status', 'Cuentas del concepto legal actualizadas.');
    }

    private function validateConcept(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:beneficio,deduccion'],
            'calculation_method' => ['required', 'in:fijo,porcentaje,formula,editable'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'formula' => ['nullable', 'string', 'max:255'],
            'account_id' => ['required', 'exists:accounting_accounts,id'],
            'applies_to_all' => ['nullable', 'boolean'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        if ($data['calculation_method'] === 'formula' && empty($data['formula'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'formula' => 'Ingrese una fórmula para calcular este concepto.',
            ]);
        }
        if ($data['calculation_method'] === 'fijo' && ! isset($data['amount'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'Ingrese el monto fijo del concepto.',
            ]);
        }
        if ($data['calculation_method'] === 'porcentaje' && ! isset($data['rate'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'rate' => 'Ingrese el porcentaje del concepto.',
            ]);
        }
        $accountType = $data['type'] === 'beneficio' ? 'gasto' : 'pasivo';
        if (! AccountingAccount::whereKey($data['account_id'])->where('level', 3)->where('type', $accountType)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'account_id' => 'Seleccione una cuenta de nivel 3 tipo ' . $accountType . '.',
            ]);
        }
        if ($data['calculation_method'] === 'formula') {
            try {
                (new \App\Services\Payroll\PayrollFormulaEvaluator())->evaluate($data['formula'], [
                    'salary' => 100,
                    'gross' => 100,
                    'overtime' => 0,
                ]);
            } catch (\InvalidArgumentException $exception) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'formula' => $exception->getMessage(),
                ]);
            }
        }

        $data['amount'] = $data['amount'] ?? 0;
        $data['rate'] = $data['rate'] ?? 0;
        $data['applies_to_all'] = $request->boolean('applies_to_all');
        $data['is_active'] = true;

        return $data;
    }

    public function exportEmployees()
    {
        $rows = Employee::orderBy('name')
            ->get()
            ->map(fn ($e) => [
                $e->code,
                $e->name,
                $e->position,
                $e->department,
                number_format($e->salary, 2),
                $e->dui,
                $e->isss_number,
                $e->afp,
                $e->hire_date?->format('Y-m-d'),
                $e->is_active ? 'Activo' : 'Inactivo',
            ]);

        return $this->streamCsv(
            'empleados-' . now()->format('Ymd') . '.csv',
            ['Código', 'Nombre', 'Cargo', 'Departamento', 'Salario', 'DUI', 'ISSS', 'AFP', 'Fecha Ingreso', 'Estado'],
            $rows
        );
    }

    public function exportPeriod(PayrollPeriod $period)
    {
        $period->load('lines.employee');

        $rows = $period->lines->map(fn ($l) => [
            $l->employee?->code,
            $l->employee?->name,
            number_format($l->salary, 2),
            number_format($l->overtime_hours, 2),
            number_format($l->overtime_amount, 2),
            number_format($l->bonuses, 2),
            number_format($l->gross_salary, 2),
            number_format($l->isss_employee, 2),
            number_format($l->afp_employee, 2),
            number_format($l->isr, 2),
            number_format($l->total_deductions, 2),
            number_format($l->net_salary, 2),
            number_format($l->isss_employer, 2),
            number_format($l->afp_employer, 2),
            number_format($l->total_employer_cost, 2),
        ]);

        return $this->streamCsv(
            'nomina-' . str($period->name)->slug() . '-' . now()->format('Ymd') . '.csv',
            [
                'Código', 'Nombre', 'Salario', 'Horas Extra', 'Monto Extra', 'Bonos', 'Salario Bruto',
                'ISSS Empleado', 'AFP Empleado', 'ISR', 'Total Deducciones', 'Salario Neto',
                'ISSS Patronal', 'AFP Patronal', 'Costo Total Patronal',
            ],
            $rows
        );
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'account_salaries_payable_id' => ['required', 'exists:accounting_accounts,id'],
            'isss_salary_cap' => ['required', 'numeric', 'min:0'],
            'isss_employee_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'isss_employer_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'afp_employee_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'afp_employer_rate' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        if (! AccountingAccount::whereKey($data['account_salaries_payable_id'])
            ->where('level', 3)->where('type', 'pasivo')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'account_salaries_payable_id' => 'Seleccione una cuenta de pasivo de nivel 3.',
            ]);
        }

        PayrollSetting::current()->update($data);

        return redirect()->route('admin.payroll.settings.index')->with('status', 'Configuración guardada.');
    }
}
