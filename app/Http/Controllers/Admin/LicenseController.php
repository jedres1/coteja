<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\License;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LicenseController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Licenses/Index', [
            'licenses'  => License::with(['company', 'plan'])->latest()->paginate(15),
            'companies' => Company::orderBy('business_name')->get(['id', 'business_name']),
            'plans'     => Plan::where('is_active', true)->orderBy('name')->get(),
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

        if ($data['license_type'] === 'propietario') {
            $data['expires_at'] = null;
        }

        $data['license_key'] = 'COT-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));

        License::create($data);

        $this->linkUsersToCompany($company);

        return back()->with('status', 'Licencia creada.');
    }

    private function linkUsersToCompany(Company $company): void
    {
        $query = User::where('role', 'inquilino')
            ->where(function ($q) use ($company) {
                $q->whereNull('customer_id');
                if ($company->customer_id) {
                    $q->orWhere('customer_id', $company->customer_id);
                }
            });

        $userIds = $query->pluck('id');
        $company->users()->syncWithoutDetaching($userIds);
    }

    public function update(Request $request, License $license)
    {
        $data = $request->validate([
            'license_type' => ['required', 'in:propietario,mensual,anual'],
            'status'       => ['required', 'in:active,expired,suspended'],
            'starts_at'    => ['required', 'date'],
            'expires_at'   => ['nullable', 'date', 'after_or_equal:starts_at', 'required_unless:license_type,propietario'],
            'max_users'    => ['required', 'integer', 'min:1'],
            'max_devices'  => ['required', 'integer', 'min:1'],
            'grace_days'   => ['required', 'integer', 'min:0'],
        ]);

        if ($data['license_type'] === 'propietario') {
            $data['expires_at'] = null;
        }

        $license->update($data);

        return back()->with('status', 'Licencia actualizada.');
    }
}
