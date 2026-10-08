<?php

namespace App\Http\Middleware;

use App\Models\Customer;
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
                return $customer ? ['id' => $customer->id, 'name' => $customer->name] : null;
            },
        ]);
    }
}
