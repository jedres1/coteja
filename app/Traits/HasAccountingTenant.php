<?php

namespace App\Traits;

use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\CostCenter;

trait HasAccountingTenant
{
    private function tenantCustomerId(): ?int
    {
        $user = auth()->user();
        if (!$user) return null;

        if ($user->isAdmin() || $user->isConsultant()) {
            $id = session('billing_tenant_id');
            return $id ? (int) $id : null;
        }

        return $user->customer_id ? (int) $user->customer_id : null;
    }

    private function requireTenant(): int
    {
        $id = $this->tenantCustomerId();

        abort_if(
            $id === null,
            422,
            'Seleccione un inquilino activo para acceder al módulo contable.'
        );

        // Inicializar catálogos en la primera visita (orden: cuentas → centros → paquetes)
        if (!AccountingAccount::customerHasCatalog($id)) {
            AccountingAccount::copyDefaultsFor($id);
        }
        if (!CostCenter::customerHasCostCenters($id)) {
            CostCenter::copyDefaultsFor($id);
        }
        if (!AccountingPackage::customerHasPackages($id)) {
            AccountingPackage::copyDefaultsFor($id);
        }

        return $id;
    }
}
