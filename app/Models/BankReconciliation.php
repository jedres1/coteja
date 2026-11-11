<?php

namespace App\Models;

use App\Enums\BankReconciliationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    protected $fillable = [
        'bank_account_id', 'period_year', 'period_month', 'cut_date',
        'statement_file_path', 'statement_balance', 'status',
        'book_balance', 'total_transit_out', 'total_transit_in', 'difference',
        'is_opening', 'reference_act', 'notes',
        'prepared_by', 'reviewed_by', 'confirmed_at',
        'voided_by', 'voided_at', 'void_reason',
        'completed_at',
    ];

    protected $casts = [
        'statement_balance' => 'decimal:2',
        'book_balance'      => 'decimal:2',
        'total_transit_out' => 'decimal:2',
        'total_transit_in'  => 'decimal:2',
        'difference'        => 'decimal:2',
        'completed_at'      => 'datetime',
        'confirmed_at'      => 'datetime',
        'voided_at'         => 'datetime',
        'period_year'       => 'integer',
        'period_month'      => 'integer',
        'is_opening'        => 'boolean',
        'cut_date'          => 'date',
        'status'            => BankReconciliationStatus::class,
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function markedTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'reconciliation_id');
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'reconciliation_id');
    }

    public function billingPayments(): HasMany
    {
        return $this->hasMany(BillingInvoicePayment::class, 'reconciliation_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function periodLabel(): string
    {
        $months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        return ($months[$this->period_month - 1] ?? '') . ' ' . $this->period_year;
    }
}
