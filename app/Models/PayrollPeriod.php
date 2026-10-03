<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'name', 'period_start', 'period_end', 'status', 'notes',
        'journal_entry_id', 'applied_at', 'applied_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'applied_at'   => 'datetime',
        'status'       => 'string',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function isApplied(): bool
    {
        return $this->status === 'aplicado';
    }
}
