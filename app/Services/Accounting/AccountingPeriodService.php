<?php

namespace App\Services\Accounting;

use App\Exceptions\AccountingPeriodException;
use App\Models\AccountingPeriod;
use App\Models\AccountingYearPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingPeriodService
{
    private static array $months = [
        '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    public function validateDateOrFail(\DateTimeInterface|string $date): void
    {
        $d      = Carbon::parse($date);
        $yearPeriod = AccountingYearPeriod::where('year', $d->year)->first();
        $period = AccountingPeriod::where('year', $d->year)->where('month', $d->month)->first();

        if (! $yearPeriod || ! $yearPeriod->isOpen() || ! $period || $period->status !== 'abierto') {
            $label = (self::$months[$d->month] ?? "Mes {$d->month}") . ' ' . $d->year;
            if ($d->month === 12) {
                $label .= ' (Cierre)';
            }
            throw new AccountingPeriodException(
                "El período contable de {$label} está cerrado. Abra el año y el mes en Contabilidad → Períodos antes de registrar transacciones."
            );
        }
    }

    public function isDateOpen(\DateTimeInterface|string $date): bool
    {
        $d = Carbon::parse($date);
        $yearPeriod = AccountingYearPeriod::where('year', $d->year)->first();
        $period = AccountingPeriod::where('year', $d->year)->where('month', $d->month)->first();
        return $yearPeriod && $yearPeriod->isOpen() && $period && $period->status === 'abierto';
    }

    public function findOrNull(int $year, int $month): ?AccountingPeriod
    {
        return AccountingPeriod::where('year', $year)->where('month', $month)->first();
    }

    public function createYearPeriods(int $year): int
    {
        return DB::transaction(function () use ($year) {
            AccountingYearPeriod::firstOrCreate(
                ['year' => $year],
                ['name' => 'Ejercicio contable '.$year, 'status' => 'cerrado']
            );

            $created = 0;
            for ($m = 1; $m <= 12; $m++) {
                $name = AccountingPeriod::labelFor($year, $m);
                $new  = AccountingPeriod::firstOrCreate(
                    ['year' => $year, 'month' => $m],
                    ['name' => $name, 'status' => 'cerrado']
                );
                if ($new->wasRecentlyCreated) {
                    $created++;
                }
            }
            return $created;
        });
    }

    public function openYear(AccountingYearPeriod $yearPeriod): void
    {
        $yearPeriod->update([
            'status' => 'abierto',
            'opened_at' => now(),
            'closed_at' => null,
            'closed_by' => null,
        ]);
    }

    public function closeYear(AccountingYearPeriod $yearPeriod, int $userId, ?string $notes = null): void
    {
        DB::transaction(function () use ($yearPeriod, $userId, $notes) {
            $yearPeriod->update([
                'status' => 'cerrado',
                'closed_at' => now(),
                'closed_by' => $userId,
                'notes' => $notes ?? $yearPeriod->notes,
            ]);

            AccountingPeriod::where('year', $yearPeriod->year)
                ->where('status', 'abierto')
                ->update([
                    'status' => 'cerrado',
                    'closed_at' => now(),
                    'closed_by' => $userId,
                    'notes' => $notes,
                    'updated_at' => now(),
                ]);
        });
    }

    public function openPeriod(AccountingPeriod $period): void
    {
        $period->update([
            'status'     => 'abierto',
            'opened_at'  => now(),
            'closed_at'  => null,
            'closed_by'  => null,
        ]);
    }

    public function closePeriod(AccountingPeriod $period, int $userId, ?string $notes = null): void
    {
        $period->update([
            'status'    => 'cerrado',
            'closed_at' => now(),
            'closed_by' => $userId,
            'notes'     => $notes ?? $period->notes,
        ]);
    }
}
