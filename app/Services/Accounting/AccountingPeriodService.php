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

    public function validateDateOrFail(\DateTimeInterface|string $date, ?int $companyId = null): void
    {
        $d = Carbon::parse($date);

        $yearQuery   = AccountingYearPeriod::where('year', $d->year);
        $periodQuery = AccountingPeriod::where('year', $d->year)->where('month', $d->month);

        if ($companyId !== null) {
            $yearQuery->where('company_id', $companyId);
            $periodQuery->where('company_id', $companyId);
        }

        $yearPeriod = $yearQuery->first();
        $period     = $periodQuery->first();

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

    public function isDateOpen(\DateTimeInterface|string $date, ?int $companyId = null): bool
    {
        $d = Carbon::parse($date);

        $yearQuery   = AccountingYearPeriod::where('year', $d->year);
        $periodQuery = AccountingPeriod::where('year', $d->year)->where('month', $d->month);

        if ($companyId !== null) {
            $yearQuery->where('company_id', $companyId);
            $periodQuery->where('company_id', $companyId);
        }

        $yearPeriod = $yearQuery->first();
        $period     = $periodQuery->first();

        return $yearPeriod && $yearPeriod->isOpen() && $period && $period->status === 'abierto';
    }

    public function findOrNull(int $year, int $month, ?int $companyId = null): ?AccountingPeriod
    {
        $query = AccountingPeriod::where('year', $year)->where('month', $month);
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }
        return $query->first();
    }

    public function createYearPeriods(int $year, ?int $companyId = null): int
    {
        return DB::transaction(function () use ($year, $companyId) {
            $yearKey    = $companyId !== null ? ['company_id' => $companyId, 'year' => $year] : ['company_id' => null, 'year' => $year];
            $yearDefaults = ['name' => 'Ejercicio contable ' . $year, 'status' => 'cerrado', 'company_id' => $companyId];

            AccountingYearPeriod::firstOrCreate($yearKey, $yearDefaults);

            $created = 0;
            for ($m = 1; $m <= 12; $m++) {
                $name       = AccountingPeriod::labelFor($year, $m);
                $periodKey  = $companyId !== null ? ['company_id' => $companyId, 'year' => $year, 'month' => $m] : ['company_id' => null, 'year' => $year, 'month' => $m];
                $new        = AccountingPeriod::firstOrCreate(
                    $periodKey,
                    ['name' => $name, 'status' => 'cerrado', 'company_id' => $companyId]
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

            $query = AccountingPeriod::where('year', $yearPeriod->year)->where('status', 'abierto');
            if ($yearPeriod->company_id !== null) {
                $query->where('company_id', $yearPeriod->company_id);
            }
            $query->update([
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
