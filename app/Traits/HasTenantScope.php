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
        if (!$company) return null;

        // Company exists but has no customer → return -1 so queries return nothing
        return $company->customer_id ? (int) $company->customer_id : -1;
    }
}
