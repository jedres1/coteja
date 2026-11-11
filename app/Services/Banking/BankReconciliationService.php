<?php

namespace App\Services\Banking;

use App\Data\ReconciliationSummary;
use App\Enums\BankReconciliationStatus;
use App\Exceptions\BankReconciliationException;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankReconciliationService
{
    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function create(
        BankAccount $account,
        Carbon      $cutDate,
        float       $statementBalance,
        User        $user,
        ?string     $notes = null,
    ): BankReconciliation {
        return DB::transaction(function () use ($account, $cutDate, $statementBalance, $user, $notes) {
            $account = BankAccount::lockForUpdate()->findOrFail($account->id);

            // RN-01: only one open reconciliation per account
            if ($account->openReconciliation()) {
                throw new BankReconciliationException(
                    'Ya existe una conciliación abierta para esta cuenta. Ciérrela o elimínela primero.'
                );
            }

            // RN-15: first reconciliation must be the opening one
            if (! $account->lastFirmReconciliation() && ! $account->reconciliations()->where('status', '!=', BankReconciliationStatus::Voided->value)->exists()) {
                throw new BankReconciliationException(
                    'La cuenta no tiene conciliación de apertura. Use crearApertura() primero.'
                );
            }

            // RN-02: cut date must be after last firm reconciliation
            if ($account->last_reconciliation_date && $cutDate->lte($account->last_reconciliation_date)) {
                throw new BankReconciliationException(
                    "La fecha de corte debe ser posterior al {$account->last_reconciliation_date->format('d/m/Y')}."
                );
            }

            return $account->reconciliations()->create([
                'period_year'       => $cutDate->year,
                'period_month'      => $cutDate->month,
                'cut_date'          => $cutDate,
                'statement_balance' => $statementBalance,
                'status'            => BankReconciliationStatus::Open,
                'notes'             => $notes,
                'prepared_by'       => $user->id,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Opening reconciliation
    // -------------------------------------------------------------------------

    public function previewOpening(
        BankAccount $account,
        Carbon      $cutDate,
        float       $statementBalance,
        array       $transitIds,
    ): array {
        $bookBalance = $account->bookBalanceAt($cutDate);

        $transitSum = (float) BankTransaction::query()
            ->where('bank_account_id', $account->id)
            ->whereIn('id', $transitIds)
            ->whereDate('transaction_date', '<=', $cutDate)
            ->sum('amount');

        $reconciledBalance = $bookBalance - $transitSum;

        return [
            'book_balance'       => $bookBalance,
            'transit_sum'        => $transitSum,
            'reconciled_balance' => $reconciledBalance,
            'statement_balance'  => $statementBalance,
            'adjustment_needed'  => $statementBalance - $reconciledBalance,
        ];
    }

    public function createOpening(
        BankAccount $account,
        Carbon      $cutDate,
        float       $statementBalance,
        array       $transitIds,
        User        $user,
        ?string     $referenceAct = null,
    ): BankReconciliation {
        return DB::transaction(function () use ($account, $cutDate, $statementBalance, $transitIds, $user, $referenceAct) {
            $account = BankAccount::lockForUpdate()->findOrFail($account->id);

            // RN-15: opening must be the first
            if ($account->reconciliations()->where('status', '!=', BankReconciliationStatus::Voided->value)->exists()) {
                throw new BankReconciliationException('La cuenta ya tiene conciliaciones. La apertura debe ser la primera.');
            }

            $recon = $account->reconciliations()->create([
                'period_year'       => $cutDate->year,
                'period_month'      => $cutDate->month,
                'cut_date'          => $cutDate,
                'statement_balance' => $statementBalance,
                'status'            => BankReconciliationStatus::Open,
                'is_opening'        => true,
                'reference_act'     => $referenceAct,
                'prepared_by'       => $user->id,
            ]);

            // RN-13: mark everything up to cut date except transit items in chunks
            BankTransaction::query()
                ->where('bank_account_id', $account->id)
                ->whereDate('transaction_date', '<=', $cutDate)
                ->whereNull('reconciliation_id')
                ->whereNotIn('id', $transitIds)
                ->chunkById(2000, fn ($chunk) =>
                    BankTransaction::whereIn('id', $chunk->pluck('id'))
                        ->update(['reconciliation_id' => $recon->id, 'is_reconciled' => true])
                );

            return $recon->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    /**
     * Candidate transactions for this reconciliation:
     * up to cut_date and not reconciled in another firm reconciliation.
     */
    public function candidates(BankReconciliation $recon): Collection
    {
        return BankTransaction::query()
            ->where('bank_account_id', $recon->bank_account_id)
            ->whereDate('transaction_date', '<=', $recon->cut_date)
            ->where(function ($q) use ($recon) {
                $q->whereNull('reconciliation_id')
                  ->orWhere('reconciliation_id', $recon->id);
            })
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    /** Pending = candidates not yet marked in this reconciliation */
    public function pending(BankReconciliation $recon): Collection
    {
        return $this->candidates($recon)->filter(fn ($t) => $t->reconciliation_id === null)->values();
    }

    // -------------------------------------------------------------------------
    // Calculate summary
    // -------------------------------------------------------------------------

    public function calculate(BankReconciliation $recon): ReconciliationSummary
    {
        $account     = $recon->bankAccount;
        $bookBalance = $account->bookBalanceAt($recon->cut_date);
        $pending     = $this->pending($recon);

        $transitOut = (float) $pending->where('amount', '<', 0)->sum('amount');
        $transitIn  = (float) $pending->where('amount', '>', 0)->sum('amount');

        $reconciledBalance = $bookBalance - ($transitOut + $transitIn);

        return new ReconciliationSummary(
            statementBalance: (float) $recon->statement_balance,
            bookBalance:       $bookBalance,
            totalTransitOut:   $transitOut,
            totalTransitIn:    $transitIn,
            reconciledBalance: $reconciledBalance,
            difference:        $reconciledBalance - (float) $recon->statement_balance,
            pendingCount:      $pending->count(),
        );
    }

    // -------------------------------------------------------------------------
    // Mark / unmark
    // -------------------------------------------------------------------------

    /** @param int[] $transactionIds */
    public function mark(BankReconciliation $recon, array $transactionIds): int
    {
        $this->ensureEditable($recon);

        return DB::transaction(function () use ($recon, $transactionIds) {
            $valid = BankTransaction::query()
                ->whereIn('id', $transactionIds)
                ->where('bank_account_id', $recon->bank_account_id)   // RN-03
                ->whereDate('transaction_date', '<=', $recon->cut_date)
                ->whereNull('reconciliation_id')
                ->lockForUpdate()
                ->pluck('id');

            if ($valid->count() !== count(array_unique($transactionIds))) {
                throw new BankReconciliationException(
                    'Algunos movimientos no se pueden marcar: pertenecen a otra cuenta, '
                    . 'tienen fecha posterior al corte o ya están conciliados.'
                );
            }

            return BankTransaction::whereIn('id', $valid)
                ->update(['reconciliation_id' => $recon->id, 'is_reconciled' => true]);
        });
    }

    /** @param int[] $transactionIds */
    public function unmark(BankReconciliation $recon, array $transactionIds): int
    {
        $this->ensureEditable($recon);

        return BankTransaction::query()
            ->whereIn('id', $transactionIds)
            ->where('reconciliation_id', $recon->id)
            ->update(['reconciliation_id' => null, 'is_reconciled' => false]);
    }

    public function updateStatementBalance(BankReconciliation $recon, float $balance): void
    {
        $this->ensureEditable($recon);
        $recon->update(['statement_balance' => $balance]);
    }

    // -------------------------------------------------------------------------
    // Put en firme (RN-04)
    // -------------------------------------------------------------------------

    public function confirm(BankReconciliation $recon, User $reviewer): BankReconciliation
    {
        $this->ensureEditable($recon);

        // RN-08 (configurable): reviewer must differ from preparer
        if (config('bank_reconciliation.require_different_reviewer', true)
            && $reviewer->id === $recon->prepared_by) {
            throw new BankReconciliationException(
                'La conciliación debe ser revisada por un usuario distinto al que la elaboró.'
            );
        }

        return DB::transaction(function () use ($recon, $reviewer) {
            $recon   = BankReconciliation::lockForUpdate()->findOrFail($recon->id);
            $summary = $this->calculate($recon);

            // RN-04
            if (! $summary->balances()) {
                throw new BankReconciliationException(sprintf(
                    'No se puede poner en firme: diferencia de %s sin explicar.',
                    number_format($summary->difference, 2)
                ));
            }

            $recon->update([
                'book_balance'      => $summary->bookBalance,
                'total_transit_out' => $summary->totalTransitOut,
                'total_transit_in'  => $summary->totalTransitIn,
                'difference'        => 0,
                'status'            => BankReconciliationStatus::Firm,
                'reviewed_by'       => $reviewer->id,
                'confirmed_at'      => now(),
                'completed_at'      => now(),
            ]);

            // RN-09: lock the period
            $recon->bankAccount->update(['last_reconciliation_date' => $recon->cut_date]);

            return $recon->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Void (RN-07)
    // -------------------------------------------------------------------------

    public function void(BankReconciliation $recon, User $user, string $reason): void
    {
        if ($recon->status !== BankReconciliationStatus::Firm) {
            throw new BankReconciliationException('Solo se pueden anular conciliaciones en firme.');
        }

        $last = $recon->bankAccount->lastFirmReconciliation();
        if ($last?->id !== $recon->id) {
            throw new BankReconciliationException('Solo se puede anular la última conciliación en firme de la cuenta.');
        }

        if ($recon->is_opening) {
            throw new BankReconciliationException('La conciliación de apertura no puede anularse desde el sistema.');
        }

        if ($recon->bankAccount->openReconciliation()) {
            throw new BankReconciliationException('Elimine primero la conciliación abierta posterior.');
        }

        DB::transaction(function () use ($recon, $user, $reason) {
            BankTransaction::where('reconciliation_id', $recon->id)
                ->update(['reconciliation_id' => null, 'is_reconciled' => false]);

            $recon->update([
                'status'      => BankReconciliationStatus::Voided,
                'voided_by'   => $user->id,
                'voided_at'   => now(),
                'void_reason' => $reason,
            ]);

            $previous = $recon->bankAccount->lastFirmReconciliation();
            $recon->bankAccount->update([
                'last_reconciliation_date' => $previous?->cut_date,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Delete (open only)
    // -------------------------------------------------------------------------

    public function delete(BankReconciliation $recon): void
    {
        $this->ensureEditable($recon);

        DB::transaction(function () use ($recon) {
            BankTransaction::where('reconciliation_id', $recon->id)
                ->update(['reconciliation_id' => null, 'is_reconciled' => false]);
            $recon->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Validate against accounting (CG check)
    // -------------------------------------------------------------------------

    public function validateAgainstAccounting(BankReconciliation $recon): ?float
    {
        $account = $recon->bankAccount;

        if (! $account->accounting_account_id) {
            return null;
        }

        $bookBalance = $account->bookBalanceAt($recon->cut_date);
        $cgBalance   = (float) \DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.accounting_account_id', $account->accounting_account_id)
            ->whereDate('journal_entries.date', '<=', $recon->cut_date)
            ->selectRaw('SUM(journal_entry_lines.debit - journal_entry_lines.credit) as balance')
            ->value('balance');

        $diff = $bookBalance - $cgBalance;

        return abs($diff) < 0.001 ? null : $diff;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function ensureEditable(BankReconciliation $recon): void
    {
        if (! $recon->isEditable()) {
            throw new BankReconciliationException(
                "La conciliación está en estado '{$recon->status->label()}' y no se puede modificar."
            );
        }
    }
}
