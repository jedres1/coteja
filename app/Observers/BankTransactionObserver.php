<?php

namespace App\Observers;

use App\Enums\BankReconciliationStatus;
use App\Exceptions\BankReconciliationException;
use App\Models\BankTransaction;

class BankTransactionObserver
{
    private const PROTECTED_FIELDS = ['amount', 'transaction_date', 'type', 'bank_account_id'];

    public function updating(BankTransaction $tx): void
    {
        // RN-10: cannot modify financial fields of a firmly reconciled transaction
        if ($tx->getOriginal('reconciliation_id') && $tx->isDirty(self::PROTECTED_FIELDS)) {
            $recon = \App\Models\BankReconciliation::find($tx->getOriginal('reconciliation_id'));
            if ($recon && ! $recon->isEditable()) {
                throw new BankReconciliationException(
                    'No se puede modificar un movimiento que ya está conciliado en firme.'
                );
            }
        }

        // RN-09: cannot move a transaction into a locked period
        if ($tx->isDirty('transaction_date')) {
            $account = $tx->bankAccount ?? \App\Models\BankAccount::find($tx->bank_account_id);
            if ($account && $account->isPeriodLocked($tx->transaction_date)) {
                throw new BankReconciliationException(
                    'No se puede mover el movimiento a un período ya conciliado.'
                );
            }
        }
    }

    public function deleting(BankTransaction $tx): bool
    {
        // RN-11: transactions are never physically deleted; use void instead
        throw new BankReconciliationException(
            'Los movimientos bancarios no se eliminan. Use la función de anulación.'
        );
    }
}
