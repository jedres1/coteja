<?php

namespace App\Traits;

trait HasTenantScope
{
    private function tenantId(): ?int
    {
        if (!auth()->user()) return null;
        $companyId = session('active_company_id');
        return $companyId ? (int) $companyId : null;
    }
}
