<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active) abort(403);

        if ($user->isInquilino() && !$request->session()->has('active_company_id')) {
            $companies = $user->accessibleCompanies;
            if ($companies->count() === 1) {
                $request->session()->put('active_company_id', $companies->first()->id);
            }
        }

        if (!$user->hasModuleAccess($module)) abort(403);

        return $next($request);
    }
}
