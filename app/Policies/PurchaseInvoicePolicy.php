<?php

namespace App\Policies;

use App\Models\PurchaseInvoice;
use App\Models\User;

class PurchaseInvoicePolicy
{
    public function delete(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->isAdmin() && ! in_array($invoice->status, ['approved', 'accounted'], true);
    }

    public function approve(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->canAccessAdmin() && $invoice->status === 'extracted';
    }
}
