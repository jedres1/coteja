<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
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
            'licenses'  => License::with(['customer', 'company', 'plan'])->latest()->paginate(15),
            'customers' => Customer::orderBy('name')->get(),
            'plans'     => Plan::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id'  => ['required', 'exists:customers,id'],
            'plan_id'      => ['required', 'exists:plans,id'],
            'license_type' => ['required', 'in:propietario,mensual,anual'],
            'environment'  => ['required', 'in:testing,production'],
            'status'       => ['required', 'in:active,expired,suspended'],
            'starts_at'    => ['required', 'date'],
            'expires_at'   => ['nullable', 'date', 'after_or_equal:starts_at', 'required_unless:license_type,propietario'],
            'max_users'    => ['required', 'integer', 'min:1'],
            'max_devices'  => ['required', 'integer', 'min:1'],
            'grace_days'   => ['required', 'integer', 'min:0'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);

        // Auto-crear empresa principal si el inquilino aún no tiene ninguna
        $company = $customer->companies()->first();
        if (!$company) {
            $company = Company::create([
                'customer_id'   => $customer->id,
                'business_name' => $customer->name,
                'trade_name'    => $customer->trade_name,
                'nit_dui'       => $customer->document_number,
                'nrc'           => $customer->nrc,
                'email'         => $customer->billing_email ?: $customer->email,
                'phone'         => $customer->billing_phone ?: $customer->phone,
                'environment'   => $data['environment'],
            ]);

            $userIds = User::where('customer_id', $customer->id)->pluck('id');
            $company->users()->syncWithoutDetaching($userIds);
        }

        if ($data['license_type'] === 'propietario') {
            $data['expires_at'] = null;
        }

        $data['company_id'] = $company->id;
        $data['license_key'] = 'COT-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        unset($data['environment']);

        License::create($data);

        $msg = $customer->companies()->count() === 1
            ? 'Licencia creada. Empresa principal generada automáticamente.'
            : 'Licencia creada.';

        return back()->with('status', $msg);
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
