<?php

namespace App\Models;

use App\Enums\BankReconciliationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $fillable = [
        'customer_id', 'company_id', 'name', 'bank_name', 'account_number',
        'account_type', 'currency', 'opening_balance', 'is_active', 'notes',
        'last_reconciliation_date', 'accounting_account_id',
    ];

    protected $casts = [
        'opening_balance'          => 'decimal:2',
        'is_active'                => 'boolean',
        'last_reconciliation_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function billingPayments(): HasMany
    {
        return $this->hasMany(BillingInvoicePayment::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('company_id', $customerId);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function openReconciliation(): ?BankReconciliation
    {
        return $this->reconciliations()
            ->where('status', BankReconciliationStatus::Open)
            ->first();
    }

    public function lastFirmReconciliation(): ?BankReconciliation
    {
        return $this->reconciliations()
            ->where('status', BankReconciliationStatus::Firm)
            ->latest('cut_date')
            ->first();
    }

    /** Book balance at a given date: opening_balance + sum of all transaction amounts */
    public function bookBalanceAt(\DateTimeInterface $date): float
    {
        $txSum = (float) $this->bankTransactions()
            ->whereDate('transaction_date', '<=', $date)
            ->sum('amount');

        return (float) $this->opening_balance + $txSum;
    }

    public function isPeriodLocked(\DateTimeInterface $date): bool
    {
        return $this->last_reconciliation_date !== null
            && $date <= $this->last_reconciliation_date;
    }
}
