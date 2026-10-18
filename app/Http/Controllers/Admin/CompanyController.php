<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    private const MODULES = [
        'billing'    => 'Facturación',
        'purchases'  => 'Compras',
        'inventory'  => 'Inventario',
        'accounting' => 'Contabilidad',
        'banking'    => 'Control Bancario',
        'payroll'    => 'Control de Nómina',
    ];

    public function index()
    {
        return Inertia::render('Admin/Companies/Index', [
            'companies' => Company::with('latestLicense.plan')->latest()->paginate(15),
            'plans'     => Plan::where('is_active', true)->orderBy('name')->get(),
            'modules'   => self::MODULES,
        ]);
    }

    public function store(Request $request)
    {
        Company::create($this->validated($request));

        return back()->with('status', 'Empresa creada.');
    }

    public function update(Request $request, Company $company)
    {
        $company->update($this->validated($request));

        return back()->with('status', 'Empresa actualizada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'business_name'   => ['required', 'string', 'max:255'],
            'trade_name'      => ['nullable', 'string', 'max:255'],
            'nit_dui'         => ['required', 'string', 'max:50'],
            'nrc'             => ['nullable', 'string', 'max:50'],
            'email'           => ['nullable', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'environment'     => ['required', 'in:testing,production'],
            'module_accesses' => ['nullable', 'array'],
            'module_accesses.*' => ['in:billing,purchases,inventory,accounting,banking,payroll'],
        ]);
    }
}
