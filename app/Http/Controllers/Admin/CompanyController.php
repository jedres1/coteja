<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        return view('admin.companies.index', [
            'companies' => Company::with('customer')->latest()->paginate(15),
            'customers' => Customer::orderBy('name')->get(),
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
