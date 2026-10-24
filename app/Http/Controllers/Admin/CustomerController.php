<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $geography = json_decode(file_get_contents(public_path('catalogs/division-geografica.json')), true) ?: [];

        return view('admin.customers.index', [
            'customers' => Customer::withCount(['companies', 'licenses'])
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('nrc', 'like', "%{$search}%");
                }))
                ->orderBy('name')->paginate(15)->withQueryString(),
            'search' => $search,
            'geography' => $geography,
        ]);
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        $password = $request->validate(['password' => ['required', 'string', 'min:8']])['password'];

        DB::transaction(function () use ($data, $password) {
            $customer = Customer::create(collect($data)->except('module_accesses')->all());
            User::create([
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'role' => 'customer',
                'is_active' => true,
                'password' => Hash::make($password),
                'module_accesses' => $data['module_accesses'] ?? ['billing'],
            ]);
        });

        return back()->with('status', 'Cliente creado.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $data = $request->validated();
        $oldEmail = $customer->email;

        DB::transaction(function () use ($customer, $data, $oldEmail) {
            $customer->update(collect($data)->except('module_accesses')->all());
            $customer->users()->where('email', $oldEmail)->update([
                'name' => $customer->name,
                'email' => $customer->email,
            ]);
        });

        return back()->with('status', 'Cliente actualizado.');
    }
}
