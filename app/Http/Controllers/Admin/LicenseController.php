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
            'company_id'   => ['required', 'exists:companies,id'],
            'plan_id'      => ['required', 'exists:plans,id'],
            'license_type' => ['required', 'in:propietario,mensual,anual'],
            'status'       => ['required', 'in:active,expired,suspended'],
            'starts_at'    => ['required', 'date'],
            'expires_at'   => ['nullable', 'date', 'after_or_equal:starts_at', 'required_unless:license_type,propietario'],
            'max_users'    => ['required', 'integer', 'min:1'],
            'max_devices'  => ['required', 'integer', 'min:1'],
            'grace_days'   => ['required', 'integer', 'min:0'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        $data['customer_id'] = $company->customer_id;
        $data['license_key'] = 'COT-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        if ($data['license_type'] === 'propietario') {
            $data['expires_at'] = null;
        }

        License::create($data);

        return back()->with('status', 'Licencia creada.');
    }

    public function update(Request $request, License $license)
    {
        $data = $request->validate([
            'license_type' => ['sometimes', 'in:propietario,mensual,anual'],
            'status'       => ['required', 'in:active,expired,suspended'],
            'expires_at'   => ['nullable', 'date'],
            'max_users'    => ['required', 'integer', 'min:1'],
            'max_devices'  => ['required', 'integer', 'min:1'],
            'grace_days'   => ['required', 'integer', 'min:0'],
        ]);

        $type = $data['license_type'] ?? $license->license_type;
        if ($type === 'propietario') {
            $data['expires_at'] = null;
        }

        $license->update($data);

        return back()->with('status', 'Licencia actualizada.');
    }
}
