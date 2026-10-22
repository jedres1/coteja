<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    private function dateRange(int $year, int $month, bool $acumulado): array
    {
        $to = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        $from = $acumulado
            ? sprintf('%04d-01-01', $year)
            : sprintf('%04d-%02d-01', $year, $month);

        return [$from, $to];
    }

    private function baseQuery(int $companyId, string $from, string $to): \Illuminate\Database\Query\Builder
    {
        return DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounting_accounts as aa', 'aa.id', '=', 'jel.account_id')
            ->where('je.company_id', $companyId)
            ->where('je.status', 'aprobado')
            ->whereNull('je.deleted_at')
            ->whereDate('je.entry_date', '>=', $from)
            ->whereDate('je.entry_date', '<=', $to);
    }

    public function availableYears(int $companyId): array
    {
        return DB::table('journal_entries')
            ->where('company_id', $companyId)
            ->where('status', 'aprobado')
            ->whereNull('deleted_at')
            ->selectRaw('YEAR(entry_date) as y')
            ->distinct()
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn($y) => (int) $y)
            ->toArray();
    }

    public function saldos(int $companyId, int $year, int $month, mixed $accountId, bool $acumulado): array
    {
        [$from, $to] = $this->dateRange($year, $month, $acumulado);

        $query = $this->baseQuery($companyId, $from, $to)
            ->select([
                'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature',
                DB::raw('SUM(jel.debit) as suma_debe'),
                DB::raw('SUM(jel.credit) as suma_haber'),
            ])
            ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature')
            ->orderBy('aa.code');

        if ($accountId) {
            $query->where('aa.id', (int) $accountId);
        }

        $balances = $query->get()->map(function ($row) {
            $debe  = (float) $row->suma_debe;
            $haber = (float) $row->suma_haber;
            return [
                'id'         => $row->id,
                'code'       => $row->code,
                'name'       => $row->name,
                'type'       => $row->type,
                'nature'     => $row->nature,
                'suma_debe'  => $debe,
                'suma_haber' => $haber,
                'saldo'      => $row->nature === 'deudora' ? $debe - $haber : $haber - $debe,
            ];
        });

        $summaryByType = $balances->groupBy('type')->map(fn($g) => [
            'suma_debe'  => $g->sum('suma_debe'),
            'suma_haber' => $g->sum('suma_haber'),
            'saldo'      => $g->sum('saldo'),
        ]);

        return compact('balances', 'summaryByType');
    }

    public function balanza(int $companyId, int $year, int $month, bool $acumulado, string $tipo, bool $soloConMovimiento): array
    {
        [$from, $to] = $this->dateRange($year, $month, $acumulado);

        $query = $this->baseQuery($companyId, $from, $to)
            ->select([
                'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature',
                DB::raw('SUM(jel.debit) as suma_debe'),
                DB::raw('SUM(jel.credit) as suma_haber'),
            ])
            ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature')
            ->orderBy('aa.code');

        if ($tipo !== '') {
            $query->where('aa.type', $tipo);
        }

        $rows = $query->get()->map(function ($row) {
            $debe  = (float) $row->suma_debe;
            $haber = (float) $row->suma_haber;
            return [
                'id'             => $row->id,
                'code'           => $row->code,
                'name'           => $row->name,
                'type'           => $row->type,
                'nature'         => $row->nature,
                'suma_debe'      => $debe,
                'suma_haber'     => $haber,
                'saldo_deudor'   => $debe > $haber ? $debe - $haber : 0.0,
                'saldo_acreedor' => $haber > $debe ? $haber - $debe : 0.0,
            ];
        });

        if ($soloConMovimiento) {
            $rows = $rows->filter(fn($r) => $r['suma_debe'] > 0 || $r['suma_haber'] > 0)->values();
        }

        return [
            'rows'               => $rows,
            'totalSumaDebe'      => $rows->sum('suma_debe'),
            'totalSumaHaber'     => $rows->sum('suma_haber'),
            'totalSaldoDeudor'   => $rows->sum('saldo_deudor'),
            'totalSaldoAcreedor' => $rows->sum('saldo_acreedor'),
        ];
    }

    public function libroMayor(int $companyId, int $year, int $month, mixed $accountId, bool $acumulado): array
    {
        [$startDate, $endDate] = $this->dateRange($year, $month, $acumulado);

        $allAccounts = AccountingAccount::forCompany($companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'nature']);

        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounting_accounts as aa', 'aa.id', '=', 'jel.account_id')
            ->where('je.company_id', $companyId)
            ->where('je.status', 'aprobado')
            ->whereNull('je.deleted_at')
            ->whereDate('je.entry_date', '>=', $startDate)
            ->whereDate('je.entry_date', '<=', $endDate)
            ->select([
                'aa.id as account_id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature',
                'je.entry_date', 'je.entry_number',
                'je.description as entry_description',
                'jel.description',
                'jel.debit', 'jel.credit',
                'jel.id as line_id',
            ])
            ->orderBy('aa.code')
            ->orderBy('je.entry_date')
            ->orderBy('je.id');

        if ($accountId) {
            $query->where('aa.id', (int) $accountId);
        }

        $ledger = $query->get()
            ->groupBy('account_id')
            ->map(function ($lines) {
                $first     = $lines->first();
                $isDeudora = $first->nature === 'deudora';
                $balance   = 0.0;

                $transactions = $lines->map(function ($line) use (&$balance, $isDeudora) {
                    $debe  = (float) $line->debit;
                    $haber = (float) $line->credit;
                    $balance += $isDeudora ? ($debe - $haber) : ($haber - $debe);
                    return [
                        'date'              => $line->entry_date,
                        'entry_number'      => $line->entry_number,
                        'entry_description' => $line->entry_description,
                        'description'       => $line->description,
                        'debit'             => $debe,
                        'credit'            => $haber,
                        'balance'           => $balance,
                    ];
                })->values();

                return [
                    'account_id'    => $first->account_id,
                    'code'          => $first->code,
                    'name'          => $first->name,
                    'type'          => $first->type,
                    'nature'        => $first->nature,
                    'transactions'  => $transactions,
                    'total_debit'   => $lines->sum('debit'),
                    'total_credit'  => $lines->sum('credit'),
                    'final_balance' => $balance,
                ];
            })
            ->values();

        return compact('ledger', 'allAccounts', 'startDate', 'endDate');
    }

    public function balanceGeneral(int $companyId, int $year, int $month): array
    {
        // Balance sheet uses all historical data up to the given date
        $to = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $rows = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('accounting_accounts as aa', 'aa.id', '=', 'jel.account_id')
            ->where('je.company_id', $companyId)
            ->where('je.status', 'aprobado')
            ->whereNull('je.deleted_at')
            ->whereDate('je.entry_date', '<=', $to)
            ->whereIn('aa.type', ['activo', 'pasivo', 'patrimonio'])
            ->select([
                'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature',
                'aa.level', 'aa.parent_id',
                DB::raw('SUM(jel.debit) as suma_debe'),
                DB::raw('SUM(jel.credit) as suma_haber'),
            ])
            ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature', 'aa.level', 'aa.parent_id')
            ->orderBy('aa.code')
            ->get()
            ->map(function ($row) {
                $debe  = (float) $row->suma_debe;
                $haber = (float) $row->suma_haber;
                return [
                    'id'         => $row->id,
                    'code'       => $row->code,
                    'name'       => $row->name,
                    'type'       => $row->type,
                    'nature'     => $row->nature,
                    'level'      => $row->level,
                    'parent_id'  => $row->parent_id,
                    'suma_debe'  => $debe,
                    'suma_haber' => $haber,
                    'saldo'      => $row->nature === 'deudora' ? $debe - $haber : $haber - $debe,
                ];
            });

        $sections  = ['activo', 'pasivo', 'patrimonio'];
        $bySection = $rows->groupBy('type')->map(fn($g) => $g->sum('saldo'));

        return compact('rows', 'bySection', 'sections');
    }

    public function estadoResultados(int $companyId, int $year, int $month, bool $acumulado): array
    {
        [$from, $to] = $this->dateRange($year, $month, $acumulado);

        $rows = $this->baseQuery($companyId, $from, $to)
            ->whereIn('aa.type', ['ingreso', 'gasto'])
            ->select([
                'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature',
                'aa.level', 'aa.parent_id',
                DB::raw('SUM(jel.debit) as suma_debe'),
                DB::raw('SUM(jel.credit) as suma_haber'),
            ])
            ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature', 'aa.level', 'aa.parent_id')
            ->orderBy('aa.code')
            ->get()
            ->map(function ($row) {
                $debe  = (float) $row->suma_debe;
                $haber = (float) $row->suma_haber;
                return [
                    'id'         => $row->id,
                    'code'       => $row->code,
                    'name'       => $row->name,
                    'type'       => $row->type,
                    'nature'     => $row->nature,
                    'level'      => $row->level,
                    'parent_id'  => $row->parent_id,
                    'suma_debe'  => $debe,
                    'suma_haber' => $haber,
                    'saldo'      => $row->nature === 'deudora' ? $debe - $haber : $haber - $debe,
                ];
            });

        $sections      = ['ingreso', 'gasto'];
        $bySection     = $rows->groupBy('type')->map(fn($g) => $g->sum('saldo'));
        $totalIngresos = (float) ($bySection->get('ingreso', 0));
        $totalGastos   = (float) ($bySection->get('gasto', 0));
        $utilidad      = $totalIngresos - $totalGastos;

        return compact('rows', 'bySection', 'sections', 'totalIngresos', 'totalGastos', 'utilidad');
    }
}
