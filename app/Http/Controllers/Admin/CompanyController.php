<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Companies/Index', [
            'companies' => Company::with('customer')->latest()->paginate(15),
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $company = Company::create($this->validated($request));

        $this->syncCustomerUsers($company);

        return back()->with('status', 'Empresa creada.');
    }

    public function update(Request $request, Company $company)
    {
        $previousCustomerId = $company->customer_id;
        $company->update($this->validated($request));

        if ($company->wasChanged('customer_id')) {
            $oldUserIds = User::where('customer_id', $previousCustomerId)->pluck('id');
            $company->users()->detach($oldUserIds);
        }

        $this->syncCustomerUsers($company);

        return back()->with('status', 'Empresa actualizada.');
    }

    private function syncCustomerUsers(Company $company): void
    {
        $userIds = User::where('customer_id', $company->customer_id)->pluck('id');
        $company->users()->syncWithoutDetaching($userIds);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'business_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'nit_dui' => ['required', 'string', 'max:50'],
            'nrc' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'environment' => ['required', 'in:testing,production'],
        ]);
    }
}
