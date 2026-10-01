<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_number', 'entry_date', 'description', 'reference',
        'status', 'created_by', 'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'entry_date'  => 'date',
        'approved_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function getTotalDebitAttribute(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return (float) $this->lines->sum('credit');
    }

    public function isEditable(): bool
    {
        return $this->status === 'borrador';
    }

    public static function nextNumber(): string
    {
        $year = now()->year;
        $max  = static::whereYear('created_at', $year)->lockForUpdate()->max('entry_number');
        $seq  = $max ? ((int) substr($max, -5)) + 1 : 1;
        return 'AS-' . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
