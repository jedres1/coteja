<?php

namespace App\Traits;

use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\CostCenter;
use Illuminate\Http\Exceptions\HttpResponseException;

trait HasAccountingTenant
{
    use HasTenantScope;

    private function tenantCustomerId(): ?int
    {
        return $this->tenantId();
    }

    private function requireTenant(): int
    {
        $id = $this->tenantId();

        if ($id === null) {
            throw new HttpResponseException(
                redirect()->route('admin.dashboard')
                    ->with('error', 'Selecciona una empresa en el selector de la barra superior para acceder al módulo contable.')
            );
        }

        // Inicializar catálogos en la primera visita (orden: cuentas → centros → paquetes)
        if (!AccountingAccount::companyHasCatalog($id)) {
            AccountingAccount::copyDefaultsFor($id);
        }
        if (!CostCenter::companyHasCostCenters($id)) {
            CostCenter::copyDefaultsFor($id);
        }
        if (!AccountingPackage::companyHasPackages($id)) {
            AccountingPackage::copyDefaultsFor($id);
        }

        return $id;
    }
}
