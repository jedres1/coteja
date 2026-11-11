<?php

namespace App\Services\Banking;

use App\Enums\BankTransactionStatus;
use App\Enums\BankTransactionType;
use App\Exceptions\BankReconciliationException;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BankTransactionService
{
    public function register(
        BankAccount          $account,
        BankTransactionType  $type,
        Carbon               $date,
        float                $amount,
        string               $description,
        User                 $user,
        ?string              $reference = null,
        bool                 $skipPeriodCheck = false,
    ): BankTransaction {
        // RN-09: period locked by last firm reconciliation
        if (! $skipPeriodCheck && $account->isPeriodLocked($date)) {
            throw new BankReconciliationException(
                "El período está conciliado hasta el {$account->last_reconciliation_date->format('d/m/Y')}. "
                . 'Registre el movimiento con una fecha posterior.'
            );
        }

        // Normalize sign based on transaction type
        $finalAmount = match ($type->sign()) {
            1  =>  abs($amount),
            -1 => -abs($amount),
            0  =>  $amount,
        };

        return DB::transaction(fn () => $account->bankTransactions()->create([
            'type'               => $type->value,
            'transaction_date'   => $date,
            'amount'             => $finalAmount,
            'reference'          => $reference,
            'notes'              => $description,
            'transaction_status' => BankTransactionStatus::Active->value,
            'created_by'         => $user->id,
        ]));
    }

    /**
     * RN-11: void via reversal movement — never physical delete.
     * Both the original and reversal are left pending so they cancel each other in the next reconciliation.
     */
    public function void(BankTransaction $tx, Carbon $voidDate, User $user, string $reason): BankTransaction
    {
        if ($tx->transaction_status === BankTransactionStatus::Voided) {
            throw new BankReconciliationException('El movimiento ya está anulado.');
        }

        if ($tx->isFirmlyReconciled()) {
            throw new BankReconciliationException(
                'El movimiento ya está conciliado en firme. No se puede anular directamente.'
            );
        }

        $account = $tx->bankAccount;

        if ($account->isPeriodLocked($voidDate)) {
            throw new BankReconciliationException('La fecha de anulación cae en un período conciliado.');
        }

        return DB::transaction(function () use ($tx, $voidDate, $user, $reason, $account) {
            $tx->update(['transaction_status' => BankTransactionStatus::Voided->value]);

            return $account->bankTransactions()->create([
                'type'               => BankTransactionType::Reversal->value,
                'transaction_date'   => $voidDate,
                'amount'             => -$tx->amount, // opposite sign
                'reference'          => $tx->reference,
                'notes'              => "Anulación de {$tx->type} {$tx->reference}: {$reason}",
                'transaction_status' => BankTransactionStatus::Active->value,
                'reversal_of_id'     => $tx->id,
                'created_by'         => $user->id,
            ]);
        });
    }
}
