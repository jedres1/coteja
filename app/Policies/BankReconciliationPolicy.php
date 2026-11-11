<?php

namespace App\Policies;

use App\Models\BankReconciliation;
use App\Models\User;

class BankReconciliationPolicy
{
    public function view(User $user, BankReconciliation $recon): bool
    {
        return $user->hasModuleAccess('banking');
    }

    public function update(User $user, BankReconciliation $recon): bool
    {
        return $user->hasModuleAccess('banking') && $recon->isEditable();
    }

    public function confirm(User $user, BankReconciliation $recon): bool
    {
        return $user->hasModuleAccess('banking') && $recon->isEditable();
    }

    public function void(User $user, BankReconciliation $recon): bool
    {
        return $user->hasModuleAccess('banking');
    }

    public function delete(User $user, BankReconciliation $recon): bool
    {
        return $user->hasModuleAccess('banking') && $recon->isEditable();
    }
}
