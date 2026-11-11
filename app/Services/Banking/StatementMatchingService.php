<?php

namespace App\Services\Banking;

use App\Models\BankReconciliation;
use App\Models\BankStatementLine;

class StatementMatchingService
{
    public function __construct(
        private readonly BankReconciliationService $reconciliation,
    ) {}

    /**
     * Suggest matches between unmatched statement lines and pending transactions.
     * Returns the number of suggestions made.
     */
    public function suggest(BankReconciliation $recon): int
    {
        $candidates = $this->reconciliation->pending($recon);
        $used       = [];
        $suggested  = 0;
        $tolerance  = config('bank_reconciliation.match_days_tolerance', 3);

        $lines = $recon->statementLines()->where('match_status', 'unmatched')->get();

        foreach ($lines as $line) {
            $available = $candidates
                ->where('amount', (float) $line->amount)
                ->reject(fn ($t) => in_array($t->id, $used));

            // Priority 1: same amount + same document reference
            $match = $line->reference
                ? $available->firstWhere('reference', $line->reference)
                : null;

            // Priority 2: same amount + date within tolerance, single candidate
            if (! $match) {
                $close = $available->filter(
                    fn ($t) => abs($t->transaction_date->diffInDays($line->line_date)) <= $tolerance
                );
                $match = $close->count() === 1 ? $close->first() : null;
            }

            if ($match) {
                $line->update(['transaction_id' => $match->id, 'match_status' => 'suggested']);
                $used[] = $match->id;
                $suggested++;
            }
        }

        return $suggested;
    }

    /** Confirm suggested matches and mark the transactions as reconciled */
    public function confirm(BankReconciliation $recon, array $lineIds): void
    {
        $lines = BankStatementLine::whereIn('id', $lineIds)
            ->where('reconciliation_id', $recon->id)
            ->where('match_status', 'suggested')
            ->get();

        $this->reconciliation->mark($recon, $lines->pluck('transaction_id')->filter()->all());

        BankStatementLine::whereIn('id', $lines->pluck('id'))
            ->update(['match_status' => 'confirmed']);
    }
}
