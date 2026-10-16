<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccount;
use App\Traits\ExportsCsv;
use App\Traits\HasTenantScope;
use App\Models\AccountingPackage;
use App\Models\Employee;
use App\Models\PayrollConcept;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayrollCalculatorService;
use App\Services\Payroll\PayrollEntryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PayrollController extends Controller
{
    use ExportsCsv, HasTenantScope;

    public function index()
    {
        $tenantId = $this->tenantId();

        return Inertia::render('Admin/Payroll/Index', [
            'employees' => Employee::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
                ->orderBy('name')->get(),
            'activeTab' => request()->query('tab') === 'nominas' ? 'nominas' : 'empleados',
            'periods'   => PayrollPeriod::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
                ->withCount('lines')
                ->withSum('lines', 'net_salary')
                ->withSum('lines', 'gross_salary')
                ->orderBy('period_start', 'desc')
                ->limit(12)
                ->get()
                ->map(fn ($p) => array_merge($p->toArray(), [
                    'total_gross' => $p->lines_sum_gross_salary,
                    'total_net'   => $p->lines_sum_net_salary,
                ])),
        ]);
    }

    public function conceptsIndex()
    {
        $tenantId = $this->tenantId();
        $this->ensureTenantPayrollSetup($tenantId);

        return Inertia::render('Admin/Payroll/Concepts', [
            'employees'     => Employee::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
                ->orderBy('name')->get(),
            'concepts'      => PayrollConcept::whereNull('system_key')
                ->when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
                ->with(['account', 'employees'])->orderBy('name')->get(),
            'legalConcepts' => PayrollConcept::whereNotNull('system_key')
                ->when($tenantId !== null,
                    fn ($q) => $q->forCustomer($tenantId),
                    fn ($q) => $q->whereNull('customer_id')
                )
                ->with(['account', 'payableAccount'])->orderBy('id')->get(),
            'accounts'      => AccountingAccount::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
                ->where('level', 3)->orderBy('code')->get(),
        ]);
    }

    public function settingsIndex()
    {
        $tenantId = $this->tenantId();
        $this->ensureTenantPayrollSetup($tenantId);

        return Inertia::render('Admin/Payroll/Settings', [
            'settings' => PayrollSetting::current($tenantId),
            'accounts' => AccountingAccount::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
                ->where('level', 3)->orderBy('code')->get(),
        ]);
    }

    public function storeEmployee(Request $request)
    {
        $tenantId = $this->tenantId();
        $codeRule = Rule::unique('employees', 'code');
        if ($tenantId !== null) $codeRule->where('customer_id', $tenantId);

        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['nullable', 'string', 'max:20', $codeRule],
            'hire_date' => ['required', 'date'],
            'salary'    => ['required', 'numeric', 'min:0'],
        ]);

        $employee = Employee::create(array_merge(
            $request->only([
                'code', 'name', 'email', 'dui', 'isss_number', 'nup', 'afp',
                'position', 'department', 'hire_date', 'salary',
                'bank_name', 'bank_account', 'is_active',
            ]),
            ['customer_id' => $tenantId]
        ));

        if (! $employee->code) {
            $employee->update(['code' => 'EMP' . str_pad($employee->id, 5, '0', STR_PAD_LEFT)]);
        }

        return back()->with('status', 'Empleado creado.');
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($employee->customer_id === $tenantId, 403);

        $codeRule = Rule::unique('employees', 'code')->ignore($employee->id);
        if ($tenantId !== null) $codeRule->where('customer_id', $tenantId);

        $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['nullable', 'string', 'max:20', $codeRule],
            'hire_date' => ['required', 'date'],
            'salary'    => ['required', 'numeric', 'min:0'],
        ]);

        $updates = $request->only([
            'name', 'email', 'dui', 'isss_number', 'nup', 'afp',
            'position', 'department', 'hire_date', 'salary',
            'bank_name', 'bank_account', 'is_active',
        ]);
        if ($request->filled('code')) {
            $updates['code'] = $request->input('code');
        }
        $employee->update($updates);

        return back()->with('status', 'Empleado actualizado.');
    }

    public function destroyEmployee(Employee $employee)
    {
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($employee->customer_id === $tenantId, 403);

        if ($employee->lines()->exists()) {
            $employee->update(['is_active' => false]);
            return back()->with('status', 'Empleado desactivado (tiene nóminas registradas).');
        }

        $employee->delete();
        return back()->with('status', 'Empleado eliminado.');
    }

    public function storePeriod(Request $request)
    {
        $tenantId = $this->tenantId();
        $this->ensureTenantPayrollSetup($tenantId);

        $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $period = PayrollPeriod::create([
            'customer_id'  => $tenantId,
            'name'         => $request->name,
            'period_start' => $request->period_start,
            'period_end'   => $request->period_end,
            'notes'        => $request->notes,
            'status'       => 'borrador',
        ]);

        $settings   = PayrollSetting::current($tenantId);
        $calculator = new PayrollCalculatorService($settings, $tenantId);
        $employees  = Employee::where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
            ->get();

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
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($period->customer_id === $tenantId, 403);

        $period->load(['lines.employee', 'journalEntry']);

        return Inertia::render('Admin/Payroll/Period', [
            'period'   => $period,
            'settings' => PayrollSetting::current($period->customer_id),
        ]);
    }

    public function updateLine(Request $request, PayrollLine $line)
    {
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($line->period->customer_id === $tenantId, 403);

        if ($line->period->isApplied()) {
            return back()->withErrors('No se pueden editar líneas de una nómina aplicada.');
        }

        $request->validate([
            'overtime_hours'   => ['nullable', 'numeric', 'min:0'],
            'overtime_amount'  => ['nullable', 'numeric', 'min:0'],
            'bonuses'          => ['nullable', 'numeric', 'min:0'],
            'concept_inputs'   => ['nullable', 'array'],
            'concept_inputs.*' => ['nullable', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string'],
        ]);

        $periodCustomerId = $line->period->customer_id;
        $settings         = PayrollSetting::current($periodCustomerId);
        $calculator       = new PayrollCalculatorService($settings, $periodCustomerId);

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
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($period->customer_id === $tenantId, 403);

        if (! $request->user()->hasModuleAccess('accounting')) {
            if ($period->status === 'aplicado') {
                return back()->withErrors('El período ya fue aplicado.');
            }
            if ($period->lines()->doesntExist()) {
                return back()->withErrors('El período no tiene líneas de nómina.');
            }
            $period->update(['status' => 'aplicado', 'applied_at' => now(), 'applied_by' => auth()->id()]);
            return back()->with('status', 'Nómina aplicada.');
        }

        try {
            $service = new PayrollEntryService();
            $entry   = $service->createFromPayroll($period, auth()->id(), $period->customer_id);

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
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($period->customer_id === $tenantId, 403);

        if ($period->status !== 'borrador') {
            return back()->withErrors('Solo se pueden eliminar períodos en estado borrador.');
        }

        $period->delete();

        return redirect()->route('admin.payroll.index')->with('status', 'Período eliminado.');
    }

    public function storeConcept(Request $request)
    {
        $tenantId = $this->tenantId();
        $data     = $this->validateConcept($request, $tenantId);

        $employeeIds = $data['employee_ids'] ?? [];
        unset($data['employee_ids']);

        $concept = PayrollConcept::create(array_merge($data, ['customer_id' => $tenantId]));

        if (! $concept->applies_to_all) {
            $concept->employees()->sync($employeeIds);
        }

        return redirect()->route('admin.payroll.concepts.index')->with('status', 'Concepto de nómina creado.');
    }

    public function updateConcept(Request $request, PayrollConcept $concept)
    {
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($concept->customer_id === $tenantId, 403);

        if ($concept->system_key) {
            return back()->withErrors('La fórmula legal se administra desde los parámetros de nómina; aquí solo se asignan sus cuentas.');
        }

        $data        = $this->validateConcept($request, $tenantId);
        $employeeIds = $data['employee_ids'] ?? [];
        unset($data['employee_ids']);

        $concept->update($data);
        $concept->employees()->sync($concept->applies_to_all ? [] : $employeeIds);

        return redirect()->route('admin.payroll.concepts.index')->with('status', 'Concepto de nómina actualizado.');
    }

    public function destroyConcept(PayrollConcept $concept)
    {
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($concept->customer_id === $tenantId, 403);

        if ($concept->system_key) {
            return back()->withErrors('Los conceptos legales no se pueden eliminar.');
        }

        $concept->delete();

        return redirect()->route('admin.payroll.concepts.index')->with('status', 'Concepto de nómina eliminado.');
    }

    public function updateConceptAccounts(Request $request, PayrollConcept $concept)
    {
        abort_unless($concept->system_key, 404);

        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($concept->customer_id === $tenantId, 403);

        $employerConcept = in_array($concept->system_key, ['isss_patronal', 'afp_patronal'], true);
        $data = $request->validate([
            'account_id'         => ['required', 'exists:accounting_accounts,id'],
            'payable_account_id' => [$employerConcept ? 'required' : 'nullable', 'exists:accounting_accounts,id'],
        ]);

        $expectedType = in_array($concept->system_key, ['salario', 'isss_patronal', 'afp_patronal'], true)
            ? 'gasto'
            : 'pasivo';

        $accountQuery = AccountingAccount::whereKey($data['account_id'])
            ->where('level', 3)->where('type', $expectedType);
        if ($tenantId !== null) $accountQuery->where('customer_id', $tenantId);

        if (! $accountQuery->exists()) {
            return back()->withErrors(['account_id' => 'Seleccione una cuenta de nivel 3 tipo ' . $expectedType . '.']);
        }

        if ($employerConcept) {
            $payableQuery = AccountingAccount::whereKey($data['payable_account_id'])
                ->where('level', 3)->where('type', 'pasivo');
            if ($tenantId !== null) $payableQuery->where('customer_id', $tenantId);

            if (! $payableQuery->exists()) {
                return back()->withErrors(['payable_account_id' => 'Seleccione una cuenta de pasivo de nivel 3.']);
            }
        }

        $concept->update([
            'account_id'         => $data['account_id'],
            'payable_account_id' => $employerConcept ? $data['payable_account_id'] : null,
        ]);

        return redirect()->route('admin.payroll.concepts.index')->with('status', 'Cuentas del concepto legal actualizadas.');
    }

    public function exportEmployees()
    {
        $tenantId = $this->tenantId();

        $rows = Employee::when($tenantId !== null, fn ($q) => $q->forCustomer($tenantId))
            ->orderBy('name')
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
        $tenantId = $this->tenantId();
        if ($tenantId) abort_unless($period->customer_id === $tenantId, 403);

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
        $tenantId = $this->tenantId();

        $data = $request->validate([
            'account_salaries_payable_id' => ['required', 'exists:accounting_accounts,id'],
            'isss_salary_cap'             => ['required', 'numeric', 'min:0'],
            'isss_employee_rate'          => ['required', 'numeric', 'min:0', 'max:1'],
            'isss_employer_rate'          => ['required', 'numeric', 'min:0', 'max:1'],
            'afp_employee_rate'           => ['required', 'numeric', 'min:0', 'max:1'],
            'afp_employer_rate'           => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        $payableQuery = AccountingAccount::whereKey($data['account_salaries_payable_id'])
            ->where('level', 3)->where('type', 'pasivo');
        if ($tenantId !== null) $payableQuery->where('customer_id', $tenantId);

        if (! $payableQuery->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'account_salaries_payable_id' => 'Seleccione una cuenta de pasivo de nivel 3.',
            ]);
        }

        PayrollSetting::current($tenantId)->update($data);

        return redirect()->route('admin.payroll.settings.index')->with('status', 'Configuración guardada.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function ensureTenantPayrollSetup(?int $customerId): void
    {
        if ($customerId === null) return;
        if (! PayrollConcept::customerHasSystemConcepts($customerId)) {
            PayrollConcept::copySystemConceptsFor($customerId);
        }
    }

    private function validateConcept(Request $request, ?int $tenantId): array
    {
        $employeeRule = Rule::exists('employees', 'id');
        if ($tenantId !== null) $employeeRule->where('customer_id', $tenantId);

        $data = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'type'               => ['required', 'in:beneficio,deduccion'],
            'calculation_method' => ['required', 'in:fijo,porcentaje,formula,editable'],
            'amount'             => ['nullable', 'numeric', 'min:0'],
            'rate'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'formula'            => ['nullable', 'string', 'max:255'],
            'account_id'         => ['required', 'exists:accounting_accounts,id'],
            'applies_to_all'     => ['nullable', 'boolean'],
            'employee_ids'       => ['nullable', 'array'],
            'employee_ids.*'     => ['integer', $employeeRule],
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

        $accountType  = $data['type'] === 'beneficio' ? 'gasto' : 'pasivo';
        $accountQuery = AccountingAccount::whereKey($data['account_id'])
            ->where('level', 3)->where('type', $accountType);
        if ($tenantId !== null) $accountQuery->where('customer_id', $tenantId);

        if (! $accountQuery->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'account_id' => 'Seleccione una cuenta de nivel 3 tipo ' . $accountType . '.',
            ]);
        }

        if ($data['calculation_method'] === 'formula') {
            try {
                (new \App\Services\Payroll\PayrollFormulaEvaluator())->evaluate($data['formula'], [
                    'salary'   => 100,
                    'gross'    => 100,
                    'overtime' => 0,
                ]);
            } catch (\InvalidArgumentException $exception) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'formula' => $exception->getMessage(),
                ]);
            }
        }

        $data['amount']       = $data['amount'] ?? 0;
        $data['rate']         = $data['rate'] ?? 0;
        $data['applies_to_all'] = $request->boolean('applies_to_all');
        $data['is_active']    = true;

        return $data;
    }
}
