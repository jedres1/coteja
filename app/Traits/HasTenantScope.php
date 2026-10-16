<?php

namespace App\Traits;

trait HasTenantScope
{
    private function tenantId(): ?int
    {
        $user = auth()->user();
        if (!$user) return null;

        if ($user->isAdmin() || $user->isConsultant()) {
            $id = session('billing_tenant_id');
            return $id ? (int) $id : null;
        }

        return $user->customer_id ? (int) $user->customer_id : null;
    }
}
