<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanySwitcherController extends Controller
{
    public function set(Request $request)
    {
        $request->validate(['company_id' => 'required|integer|exists:companies,id']);
        $companyId = $request->integer('company_id');
        $user = $request->user();

        if ($user->isAdmin() || $user->accessibleCompanies()->where('companies.id', $companyId)->exists()) {
            $request->session()->put('active_company_id', $companyId);
        } else {
            abort(403);
        }

        // The saved intended URL may belong to another company or a disabled module.
        // Always enter through a module enabled for the company just selected.
        $request->session()->forget('url.intended');

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard')->with('status', 'Empresa activa actualizada.');
        }

        $company = Company::findOrFail($companyId);
        $moduleRoutes = [
            'purchases' => 'admin.purchase-invoices.index',
            'billing' => 'admin.factura-sv.billing.facturas',
            'accounting' => 'admin.accounting.diario.index',
            'banking' => 'admin.bank-transactions.index',
            'inventory' => 'admin.inventory.movements',
            'payroll' => 'admin.payroll.index',
        ];

        foreach ($moduleRoutes as $module => $routeName) {
            if (in_array($module, $company->module_accesses ?? [], true)) {
                return redirect()->route($routeName)->with('status', 'Empresa activa actualizada.');
            }
        }

        $request->session()->forget('active_company_id');
        return redirect()->route('admin.seleccionar-empresa')
            ->with('error', 'La empresa seleccionada no tiene módulos habilitados. Contacta al administrador.');
    }

    public function clear(Request $request)
    {
        $request->session()->forget('active_company_id');
        return back();
    }
}
