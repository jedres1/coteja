<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                    'is_inquilino'     => $user->isInquilino(),
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
                'error'  => fn () => $request->session()->get('error'),
            ],
            'activeCompany' => function () use ($request, $user) {
                if (! $user || ! $user->canAccessAdmin()) return null;

                $companyId = $request->session()->get('active_company_id');

                // Auto-select the only company for single-company inquilinos
                if (! $companyId && $user->isInquilino()) {
                    $companies = $user->accessibleCompanies;
                    if ($companies->count() === 1) {
                        $companyId = $companies->first()->id;
                        $request->session()->put('active_company_id', $companyId);
                    }
                }

                if (! $companyId) return null;

                $company = Company::find($companyId, ['id', 'business_name', 'module_accesses']);
                if (! $company) return null;

                if ($user->isInquilino() && ! $user->accessibleCompanies()->where('companies.id', $companyId)->exists()) {
                    $request->session()->forget('active_company_id');
                    return null;
                }

                return [
                    'id'      => $company->id,
                    'name'    => $company->business_name,
                    'modules' => $company->module_accesses ?? [],
                ];
            },
            'availableCompanies' => function () use ($user) {
                if (! $user || ! $user->canAccessAdmin()) return [];
                if ($user->isAdmin()) {
                    return Company::orderBy('business_name')
                        ->get(['id', 'business_name'])
                        ->map(fn ($c) => ['id' => $c->id, 'name' => $c->business_name])
                        ->toArray();
                }
                return $user->accessibleCompanies()
                    ->orderBy('business_name')
                    ->get(['companies.id', 'companies.business_name'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->business_name])
                    ->toArray();
            },
            'activeExtraction' => function () use ($user) {
                if (! $user || ! $user->canAccessAdmin()) return null;

                $run = DB::table('purchase_invoice_extractions')
                    ->where('user_id', $user->id)
                    ->where('updated_at', '>=', now()->subMinutes(10))
                    ->latest('created_at')
                    ->first();

                return $run ? [
                    'id' => $run->id,
                    'status' => $run->status,
                    'from' => $run->date_from,
                    'to' => $run->date_to,
                    'total' => (int) $run->total_messages,
                    'reviewed' => (int) $run->reviewed,
                    'imported' => (int) $run->imported,
                    'duplicates' => (int) $run->duplicates,
                    'errors' => (int) $run->errors,
                ] : null;
            },
        ]);
    }
}
