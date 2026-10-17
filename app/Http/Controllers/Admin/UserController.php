<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $role = $request->query('role');

        return Inertia::render('Admin/Users/Index', [
            'users' => User::with(['customer', 'accessibleCompanies'])
                ->when($role, fn ($query) => $query->where('role', $role))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('document_number', 'like', "%{$search}%");
                            });
                    });
                })
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'customers' => Customer::with('companies')->orderBy('name')->get(),
            'companies' => Company::with('customer')->orderBy('business_name')->get(),
            'modules' => $this->availableCustomerModules(),
            'search' => $search,
            'role' => $role,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['password'] = Hash::make($data['password']);
        $companyIds = $data['company_ids'] ?? [];
        unset($data['company_ids']);

        $user = User::create($data);
        $user->accessibleCompanies()->sync($companyIds);

        return back()->with('status', 'Usuario creado.');
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validatedData($request, $user);

        if (($data['is_active'] ?? false) === false && $user->is(auth()->user())) {
            return back()->withErrors('No puede desactivar su propio usuario.');
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $companyIds = $data['company_ids'] ?? [];
        unset($data['company_ids']);

        $user->update($data);
        $user->accessibleCompanies()->sync($companyIds);

        return back()->with('status', 'Usuario actualizado.');
    }

    public function destroy(User $user)
    {
        if ($user->is(auth()->user())) {
            return back()->withErrors('No puede eliminar su propio usuario.');
        }

        $user->delete();

        return back()->with('status', 'Usuario eliminado.');
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $isUpdate = $user !== null;

        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', 'in:admin,inquilino'],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$isUpdate ? 'nullable' : 'required', 'string', 'min:8'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'exists:companies,id'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        if ($data['role'] === 'admin') {
            $data['customer_id'] = null;
            $data['company_ids'] = [];
        } else {
            $data['company_ids'] = array_values($data['company_ids'] ?? []);
        }

        if ($data['role'] === 'inquilino' && empty($data['customer_id'])) {
            validator([], [])->after(function ($validator) {
                $validator->errors()->add('customer_id', 'Seleccione el inquilino vinculado.');
            })->validate();
        }

        if ($data['role'] === 'inquilino' && empty($data['company_ids'])) {
            validator([], [])->after(function ($validator) {
                $validator->errors()->add('company_ids', 'Seleccione al menos una empresa para este usuario.');
            })->validate();
        }

        if ($data['role'] === 'inquilino' && !empty($data['company_ids'])) {
            $validCompanyIds = Company::where('customer_id', $data['customer_id'])
                ->whereIn('id', $data['company_ids'])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($validCompanyIds) !== count($data['company_ids'])) {
                validator([], [])->after(function ($validator) {
                    $validator->errors()->add('company_ids', 'Todas las empresas seleccionadas deben pertenecer al inquilino vinculado.');
                })->validate();
            }
        }

        return $data;
    }

    private function availableCustomerModules(): array
    {
        return [
            'billing'    => 'Facturación',
            'purchases'  => 'Compras',
            'inventory'  => 'Inventario',
            'accounting' => 'Contabilidad',
            'banking'    => 'Control Bancario',
            'payroll'    => 'Control de Nómina',
        ];
    }
}
