<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanySelectionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // If already selected (and valid), go straight to dashboard
        if ($request->session()->has('active_company_id')) {
            $companyId = (int) $request->session()->get('active_company_id');
            if ($user->accessibleCompanies()->where('companies.id', $companyId)->exists()) {
                return redirect()->route('admin.dashboard');
            }
            $request->session()->forget('active_company_id');
        }

        return Inertia::render('Admin/SelectCompany');
    }
}
