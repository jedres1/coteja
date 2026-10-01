<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'year', 'month', 'name', 'status',
        'opened_at', 'closed_at', 'closed_by', 'notes',
    ];

    protected $casts = [
        'year'      => 'integer',
        'month'     => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    private static array $monthNames = [
        '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'abierto';
    }

    public function startDate(): string
    {
        return sprintf('%04d-%02d-01', $this->year, $this->month);
    }

    public function endDate(): string
    {
        return \Carbon\Carbon::create($this->year, $this->month, 1)->endOfMonth()->toDateString();
    }

    public static function labelFor(int $year, int $month): string
    {
        $name = (self::$monthNames[$month] ?? "Mes {$month}") . " {$year}";
        return $month === 12 ? $name . ' (Cierre)' : $name;
    }

    public function getFullNameAttribute(): string
    {
        return self::labelFor($this->year, $this->month);
    }
}
