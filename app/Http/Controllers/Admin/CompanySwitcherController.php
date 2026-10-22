<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        }

        // Honor the intended URL stored by EnsureCompanySelected when it forced a redirect
        if ($request->session()->has('url.intended')) {
            return redirect()->intended()->with('status', 'Empresa activa actualizada.');
        }

        // Coming from the selection page: go to role-appropriate home instead of back()
        $previousUrl = url()->previous();
        if (str_contains($previousUrl, route('admin.seleccionar-empresa'))) {
            return redirect()->route('admin.seleccionar-empresa')->with('status', 'Empresa activa actualizada.');
        }

        return back()->with('status', 'Empresa activa actualizada.');
    }

    public function clear(Request $request)
    {
        $request->session()->forget('active_company_id');
        return back();
    }
}
