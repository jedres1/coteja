<?php

namespace App\Data;

final class ReconciliationSummary
{
    public function __construct(
        public readonly float $statementBalance,
        public readonly float $bookBalance,
        public readonly float $totalTransitOut,   // negative (pending checks/payments)
        public readonly float $totalTransitIn,    // positive (pending deposits)
        public readonly float $reconciledBalance, // bookBalance − Σ pending
        public readonly float $difference,        // reconciledBalance − statementBalance
        public readonly int   $pendingCount,
    ) {}

    public function balances(): bool
    {
        return abs($this->difference) < 0.001;
    }
}
