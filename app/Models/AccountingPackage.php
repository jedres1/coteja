<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPackage extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'type',
        'debit_account_id', 'credit_account_id',
        'secondary_debit_account_id', 'secondary_credit_account_id',
        'last_correlative', 'is_active',
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'last_correlative' => 'integer',
    ];

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'credit_account_id');
    }

    public function secondaryDebitAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'secondary_debit_account_id');
    }

    public function secondaryCreditAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'secondary_credit_account_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    // Must be called inside a DB::transaction()
    public function reserveNextNumber(): string
    {
        $pkg = static::where('id', $this->id)->lockForUpdate()->first();
        $pkg->increment('last_correlative');
        $this->last_correlative = $pkg->last_correlative;
        return $this->code . str_pad($pkg->last_correlative, 9, '0', STR_PAD_LEFT);
    }
}
