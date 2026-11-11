<?php

namespace App\Models;

use App\Enums\BankReconciliationStatus;
use App\Enums\BankTransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BankTransaction extends Model
{
    protected $fillable = [
        'bank_account_id', 'type', 'transaction_date', 'bank_account',
        'amount', 'reference', 'notes', 'reconciliation_id', 'is_reconciled',
        'reversal_of_id', 'transaction_status', 'journal_entry_id', 'created_by',
    ];

    protected $casts = [
        'transaction_date'   => 'date',
        'amount'             => 'decimal:2',
        'is_reconciled'      => 'boolean',
        'transaction_status' => BankTransactionStatus::class,
    ];

    public function purchaseInvoices(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseInvoice::class, 'bank_transaction_purchase_invoice')
            ->withPivot('amount_applied')
            ->withTimestamps();
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'reversal_of_id');
    }

    public function isFirmlyReconciled(): bool
    {
        return $this->reconciliation_id !== null
            && $this->reconciliation?->status === BankReconciliationStatus::Firm;
    }

    /** Transactions not reconciled in a firm reconciliation */
    public function scopePending(Builder $q): Builder
    {
        return $q->where(function (Builder $q) {
            $q->whereNull('reconciliation_id')
              ->orWhereHas('reconciliation', fn ($r) => $r->where('status', BankReconciliationStatus::Open->value));
        });
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('transaction_status', BankTransactionStatus::Active->value);
    }
}
