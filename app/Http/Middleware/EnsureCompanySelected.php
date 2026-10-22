<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            return $next($request);
        }

        // Admins operate without mandatory company pre-selection
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Already has a valid company in session
        if ($request->session()->has('active_company_id')) {
            $companyId = (int) $request->session()->get('active_company_id');
            if ($user->accessibleCompanies()->where('companies.id', $companyId)->exists()) {
                return $next($request);
            }
            // Stale or forbidden company — clear it and re-evaluate
            $request->session()->forget('active_company_id');
        }

        $companies = $user->accessibleCompanies;

        if ($companies->isEmpty()) {
            abort(403, 'No tienes empresas asignadas. Contacta al administrador.');
        }

        if ($companies->count() === 1) {
            $request->session()->put('active_company_id', $companies->first()->id);
            return $next($request);
        }

        // Multiple companies and none selected: store intended URL and redirect to selection
        return redirect()->guest(route('admin.seleccionar-empresa'));
    }
}
