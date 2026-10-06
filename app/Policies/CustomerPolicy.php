<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function delete(User $user, Customer $customer): bool
    {
        // Solo admin puede borrar; no si tiene licencias activas
        return $user->isAdmin() && $customer->licenses()->where('status', 'active')->doesntExist();
    }
}
