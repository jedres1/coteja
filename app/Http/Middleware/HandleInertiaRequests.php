<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'               => $user->id,
                    'name'             => $user->name,
                    'email'            => $user->email,
                    'role'             => $user->role,
                    'can_access_admin' => $user->canAccessAdmin(),
                    'is_admin'         => $user->isAdmin(),
                    'modules'          => [
                        'purchases'  => $user->hasModuleAccess('purchases'),
                        'banking'    => $user->hasModuleAccess('banking'),
                        'accounting' => $user->hasModuleAccess('accounting'),
                        'payroll'    => $user->hasModuleAccess('payroll'),
                        'billing'    => $user->hasModuleAccess('billing'),
                        'inventory'  => $user->hasModuleAccess('inventory'),
                    ],
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            'billingTenant' => function () use ($request, $user) {
                if (! $user || ! $user->canAccessAdmin()) {
                    return null;
                }
                $tenantId = $request->session()->get('billing_tenant_id');
                if (! $tenantId) {
                    return null;
                }
                $customer = Customer::find($tenantId, ['id', 'name']);
                if (! $customer) {
                    return null;
                }
                $modules = User::where('customer_id', $tenantId)
                    ->whereNotNull('module_accesses')
                    ->pluck('module_accesses')
                    ->flatten()
                    ->unique()
                    ->values()
                    ->toArray();
                return ['id' => $customer->id, 'name' => $customer->name, 'modules' => $modules];
            },
            'availableCustomers' => $user && $user->isAdmin()
                ? Customer::where('status', 'active')->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
                    ->toArray()
                : [],
        ]);
    }
}
