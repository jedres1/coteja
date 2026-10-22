<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanySelectionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($request->session()->has('active_company_id')) {
            $companyId = (int) $request->session()->get('active_company_id');
            if ($user->accessibleCompanies()->where('companies.id', $companyId)->exists()) {
                return redirect($this->homeFor($user, $companyId));
            }
            $request->session()->forget('active_company_id');
        }

        return Inertia::render('Admin/SelectCompany');
    }

    private function homeFor($user, int $companyId): string
    {
        if ($user->isAdmin()) {
            return route('admin.dashboard');
        }

        // Redirect inquilino to the first module the selected company has enabled
        $company = Company::find($companyId);
        $modules = $company?->module_accesses ?? [];

        $moduleRoutes = [
            'purchases'  => 'admin.purchase-invoices.index',
            'billing'    => 'admin.factura-sv.billing.facturas',
            'accounting' => 'admin.accounting.diario.index',
            'banking'    => 'admin.bank-transactions.index',
            'inventory'  => 'admin.inventory.movements',
            'payroll'    => 'admin.payroll.index',
        ];

        foreach ($moduleRoutes as $module => $routeName) {
            if (in_array($module, $modules, true)) {
                return route($routeName);
            }
        }

        // Fallback: stay on selection page (company has no modules enabled)
        return route('admin.seleccionar-empresa');
    }
}
