<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $geography = json_decode(file_get_contents(public_path('catalogs/division-geografica.json')), true) ?: [];

        return view('admin.customers.index', [
            'customers' => Customer::withCount(['companies', 'licenses'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('document_number', 'like', "%{$search}%")
                            ->orWhere('nrc', 'like', "%{$search}%")
                            ->orWhere('business_activity', 'like', "%{$search}%")
                            ->orWhere('activity_description', 'like', "%{$search}%")
                            ->orWhere('billing_email', 'like', "%{$search}%")
                            ->orWhere('billing_phone', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'search' => $search,
            'geography' => $geography,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'document_type' => ['required', 'in:13,36,37,03,02'],
            'document_number' => ['required', 'string', 'max:50'],
            'nrc' => ['nullable', 'string', 'max:50'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'business_activity' => ['nullable', 'string', 'max:10'],
            'activity_description' => ['nullable', 'string', 'max:255'],
            'address_department' => ['required', 'string', 'size:2'],
            'address_municipality' => ['required', 'string', 'between:2,4'],
            'address' => ['required', 'string', 'max:500'],
            'preferred_dte_type' => ['required', 'in:01,03,05,06,07,11,14'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:active,suspended,prospect'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $customer = Customer::create($data);

        User::create([
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'role' => 'customer',
            'is_active' => $customer->status === 'active',
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('status', 'Cliente creado.');
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email,'.$customer->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'document_type' => ['required', 'in:13,36,37,03,02'],
            'document_number' => ['required', 'string', 'max:50'],
            'nrc' => ['nullable', 'string', 'max:50'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'business_activity' => ['nullable', 'string', 'max:10'],
            'activity_description' => ['nullable', 'string', 'max:255'],
            'address_department' => ['required', 'string', 'size:2'],
            'address_municipality' => ['required', 'string', 'between:2,4'],
            'address' => ['required', 'string', 'max:500'],
            'preferred_dte_type' => ['required', 'in:01,03,05,06,07,11,14'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:active,suspended,prospect'],
        ]);

        $customer->update($data);
        $customer->users()->update(['is_active' => $customer->status === 'active']);

        return back()->with('status', 'Cliente actualizado.');
    }
}
