<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\License;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LicenseController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Licenses/Index', [
            'licenses' => License::with(['customer', 'company', 'plan'])->latest()->paginate(15),
            'companies' => Company::with('customer')->orderBy('business_name')->get(),
            'plans' => Plan::where('is_active', true)->orderBy('name')->get(),
            'licenseStatuses' => ['active' => 'Activa', 'expired' => 'Vencida', 'suspended' => 'Suspendida'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'status' => ['required', 'in:active,expired,suspended'],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'max_users' => ['required', 'integer', 'min:1'],
            'max_devices' => ['required', 'integer', 'min:1'],
            'grace_days' => ['required', 'integer', 'min:0'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        $data['customer_id'] = $company->customer_id;
        $data['license_key'] = 'COT-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        License::create($data);

        return back()->with('status', 'Licencia creada.');
    }

    public function update(Request $request, License $license)
    {
        $license->update($request->validate([
            'status' => ['required', 'in:active,expired,suspended'],
            'expires_at' => ['required', 'date'],
            'max_users' => ['required', 'integer', 'min:1'],
            'max_devices' => ['required', 'integer', 'min:1'],
            'grace_days' => ['required', 'integer', 'min:0'],
        ]));

        return back()->with('status', 'Licencia actualizada.');
    }
}
