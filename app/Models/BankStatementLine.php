<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'reconciliation_id', 'line_date', 'reference', 'description',
        'amount', 'transaction_id', 'match_status',
    ];

    protected $casts = [
        'line_date' => 'date',
        'amount'    => 'decimal:2',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'transaction_id');
    }
}
