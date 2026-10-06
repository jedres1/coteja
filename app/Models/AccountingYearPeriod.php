<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingYearPeriod extends Model
{
    protected $fillable = [
        'year', 'name', 'status', 'opened_at', 'closed_at', 'closed_by', 'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function months(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'year', 'year')->orderBy('month');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'abierto';
    }
}
