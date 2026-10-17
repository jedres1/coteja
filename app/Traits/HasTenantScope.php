<?php

namespace App\Traits;

trait HasTenantScope
{
    private function tenantId(): ?int
    {
        $user = auth()->user();
        if (!$user) return null;

        $companyId = session('active_company_id');
        if (!$companyId) return null;

        $company = \App\Models\Company::find($companyId, ['customer_id']);
        return $company?->customer_id ? (int) $company->customer_id : null;
    }
}
