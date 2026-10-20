<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    private const TTL_OPEN   = 900;   // 15 min for open periods
    private const TTL_CLOSED = 86400; // 24 h for closed periods

    private function ttl(int $year, int $month): int
    {
        $status = Cache::remember(
            "acct_period_status:{$year}:{$month}",
            300,
            fn () => AccountingPeriod::where('year', $year)->where('month', $month)->value('status') ?? 'abierto'
        );
        return $status !== 'abierto' ? self::TTL_CLOSED : self::TTL_OPEN;
    }

    private function version(int $companyId): int
    {
        return (int) Cache::get("acct_v:{$companyId}", 0);
    }

    private function monthExpr(string $col): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', {$col}) AS INTEGER)"
            : "MONTH({$col})";
    }

    private function key(string $report, int $companyId, array $params): string
    {
        return 'acct:' . $this->version($companyId) . ":{$companyId}:{$report}:" . md5(serialize($params));
    }

    public function invalidateCompany(int $companyId): void
    {
        Cache::increment("acct_v:{$companyId}");
    }

    public function availableYears(): array
    {
        $years = AccountingPeriod::distinct()->orderByDesc('year')->pluck('year')->toArray();
        if (! in_array(now()->year, $years)) {
            $years[] = now()->year;
            rsort($years);
        }
        return $years;
    }

    public function saldos(int $companyId, int $year, int $month, ?string $accountId, bool $acumulado): array
    {
        $key = $this->key('saldos', $companyId, compact('year', 'month', 'accountId', 'acumulado'));
        $ttl = $this->ttl($year, $month);

        return Cache::remember($key, $ttl, function () use ($companyId, $year, $month, $accountId, $acumulado) {
            $query = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je',     'je.id', '=', 'jel.journal_entry_id')
                ->join('accounting_accounts as aa', 'aa.id', '=', 'jel.account_id')
                ->where('je.status', 'aprobado')
                ->where('aa.company_id', $companyId)
                ->when($accountId, fn ($q) => $q->where('jel.account_id', $accountId))
                ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature')
                ->select([
                    'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature',
                    DB::raw('SUM(jel.debit)  as total_debit'),
                    DB::raw('SUM(jel.credit) as total_credit'),
                ])
                ->orderBy('aa.code');

            if ($acumulado) {
                $query->whereYear('je.entry_date', $year)->whereRaw($this->monthExpr('je.entry_date') . ' <= ?', [$month]);
            } else {
                $query->whereYear('je.entry_date', $year)->whereMonth('je.entry_date', $month);
            }

            $balances = $query->get()->map(function ($row) {
                $debe  = (float) $row->total_debit;
                $haber = (float) $row->total_credit;
                $saldo = $row->nature === 'deudora' ? $debe - $haber : $haber - $debe;
                return (object) [
                    'id'           => $row->id,
                    'code'         => $row->code,
                    'name'         => $row->name,
                    'type'         => $row->type,
                    'nature'       => $row->nature,
                    'total_debit'  => $debe,
                    'total_credit' => $haber,
                    'saldo'        => $saldo,
                ];
            });

            $summaryByType = $balances->groupBy('type')->map(fn ($g) => [
                'total_debit'  => $g->sum('total_debit'),
                'total_credit' => $g->sum('total_credit'),
                'saldo'        => $g->sum('saldo'),
            ]);

            return compact('balances', 'summaryByType');
        });
    }

    public function balanza(
        int $companyId, int $year, int $month,
        bool $acumulado, string $tipo, bool $soloConMovimiento
    ): array {
        $key = $this->key('balanza', $companyId, compact('year', 'month', 'acumulado', 'tipo', 'soloConMovimiento'));
        $ttl = $this->ttl($year, $month);

        return Cache::remember($key, $ttl, function () use ($companyId, $year, $month, $acumulado, $tipo, $soloConMovimiento) {
            $movsSub = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('je.company_id', $companyId)
                ->where('je.status', 'aprobado')
                ->whereYear('je.entry_date', $year)
                ->when($acumulado,
                    fn ($q) => $q->whereRaw($this->monthExpr('je.entry_date') . ' <= ?', [$month]),
                    fn ($q) => $q->whereMonth('je.entry_date', $month)
                )
                ->groupBy('jel.account_id')
                ->select([
                    'jel.account_id',
                    DB::raw('SUM(jel.debit)  as suma_debe'),
                    DB::raw('SUM(jel.credit) as suma_haber'),
                ]);

            $rows = DB::table('accounting_accounts as aa')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('accounting_accounts as ch')
                    ->whereColumn('ch.parent_id', 'aa.id'))
                ->where('aa.is_active', true)
                ->where('aa.company_id', $companyId)
                ->when($tipo !== '', fn ($q) => $q->where('aa.type', $tipo))
                ->leftJoinSub($movsSub, 'movs', 'movs.account_id', '=', 'aa.id')
                ->when($soloConMovimiento, fn ($q) => $q->whereNotNull('movs.account_id'))
                ->select([
                    'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature',
                    DB::raw('COALESCE(movs.suma_debe,  0) as suma_debe'),
                    DB::raw('COALESCE(movs.suma_haber, 0) as suma_haber'),
                ])
                ->orderBy('aa.code')
                ->get()
                ->map(function ($r) {
                    $debe = (float) $r->suma_debe;
                    $haber = (float) $r->suma_haber;
                    $neto  = $debe - $haber;
                    return (object) [
                        'id'               => $r->id,
                        'code'             => $r->code,
                        'name'             => $r->name,
                        'type'             => $r->type,
                        'nature'           => $r->nature,
                        'suma_debe'        => $debe,
                        'suma_haber'       => $haber,
                        'saldo_deudor'     => $neto > 0 ? $neto      : 0.0,
                        'saldo_acreedor'   => $neto < 0 ? abs($neto) : 0.0,
                        'tiene_movimiento' => ($debe + $haber) > 0,
                    ];
                });

            $conMovimiento      = $rows->filter(fn ($r) => $r->tiene_movimiento);
            $totalSumaDebe      = $conMovimiento->sum('suma_debe');
            $totalSumaHaber     = $conMovimiento->sum('suma_haber');
            $totalSaldoDeudor   = $conMovimiento->sum('saldo_deudor');
            $totalSaldoAcreedor = $conMovimiento->sum('saldo_acreedor');

            return compact('rows', 'totalSumaDebe', 'totalSumaHaber', 'totalSaldoDeudor', 'totalSaldoAcreedor');
        });
    }

    public function libroMayor(int $companyId, int $year, int $month, ?string $accountId, bool $acumulado): array
    {
        $key = $this->key('libro_mayor', $companyId, compact('year', 'month', 'accountId', 'acumulado'));
        $ttl = $this->ttl($year, $month);

        return Cache::remember($key, $ttl, function () use ($companyId, $year, $month, $accountId, $acumulado) {
            $startDate = $acumulado
                ? sprintf('%04d-01-01', $year)
                : sprintf('%04d-%02d-01', $year, $month);
            $endDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $accountsQuery = DB::table('accounting_accounts as aa')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('accounting_accounts as ch')
                    ->whereColumn('ch.parent_id', 'aa.id'))
                ->where('aa.is_active', true)
                ->where('aa.company_id', $companyId)
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('journal_entry_lines as jel')
                    ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                    ->whereColumn('jel.account_id', 'aa.id')
                    ->where('je.status', 'aprobado')
                    ->whereBetween('je.entry_date', [$startDate, $endDate]))
                ->when($accountId, fn ($q) => $q->where('aa.id', $accountId))
                ->orderBy('aa.code')
                ->get(['aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature']);

            $accountIds = $accountsQuery->pluck('id');

            $saldosIniciales = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->whereIn('jel.account_id', $accountIds)
                ->where('je.company_id', $companyId)
                ->where('je.status', 'aprobado')
                ->where('je.entry_date', '<', $startDate)
                ->groupBy('jel.account_id')
                ->select([
                    'jel.account_id',
                    DB::raw('SUM(jel.debit)  as total_debit'),
                    DB::raw('SUM(jel.credit) as total_credit'),
                ])
                ->get()
                ->keyBy('account_id');

            $movements = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je',         'je.id', '=', 'jel.journal_entry_id')
                ->leftJoin('accounting_packages as ap', 'ap.id', '=', 'je.accounting_package_id')
                ->whereIn('jel.account_id', $accountIds)
                ->where('je.company_id', $companyId)
                ->where('je.status', 'aprobado')
                ->whereBetween('je.entry_date', [$startDate, $endDate])
                ->orderBy('jel.account_id')
                ->orderBy('je.entry_date')
                ->orderBy('je.id')
                ->select([
                    'jel.account_id',
                    'je.id as entry_id',
                    'je.entry_number',
                    'je.entry_date',
                    'je.description',
                    'je.source_document',
                    'ap.code as package_code',
                    DB::raw('jel.debit  as debe'),
                    DB::raw('jel.credit as haber'),
                ])
                ->get()
                ->groupBy('account_id');

            $ledger = $accountsQuery->map(function ($account) use ($saldosIniciales, $movements) {
                $si       = $saldosIniciales->get($account->id);
                $siDebit  = $si ? (float) $si->total_debit  : 0.0;
                $siCredit = $si ? (float) $si->total_credit : 0.0;

                $saldoInicial = $account->nature === 'deudora'
                    ? $siDebit - $siCredit
                    : $siCredit - $siDebit;

                $saldoCorrente = $saldoInicial;
                $lines = collect($movements->get($account->id, []))->map(function ($m) use ($account, &$saldoCorrente) {
                    $debe  = (float) $m->debe;
                    $haber = (float) $m->haber;
                    $saldoCorrente += $account->nature === 'deudora' ? $debe - $haber : $haber - $debe;
                    return (object) [
                        'entry_id'        => $m->entry_id,
                        'entry_number'    => $m->entry_number,
                        'entry_date'      => $m->entry_date,
                        'description'     => $m->description,
                        'source_document' => $m->source_document,
                        'package_code'    => $m->package_code,
                        'debe'            => $debe,
                        'haber'           => $haber,
                        'saldo'           => $saldoCorrente,
                    ];
                });

                return (object) [
                    'account'       => $account,
                    'saldo_inicial' => $saldoInicial,
                    'saldo_final'   => $saldoCorrente,
                    'total_debe'    => $lines->sum('debe'),
                    'total_haber'   => $lines->sum('haber'),
                    'lines'         => $lines,
                ];
            });

            $allAccounts = AccountingAccount::forCompany($companyId)
                ->leafAccounts()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']);

            return compact('ledger', 'allAccounts', 'startDate', 'endDate');
        });
    }

    public function balanceGeneral(int $companyId, int $year, int $month): array
    {
        $key = $this->key('balance_general', $companyId, compact('year', 'month'));
        $ttl = $this->ttl($year, $month);

        return Cache::remember($key, $ttl, function () use ($companyId, $year, $month) {
            $sections = AccountingAccount::forCompany($companyId)
                ->whereIn('type', ['activo', 'pasivo', 'patrimonio'])
                ->where('level', 2)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']);

            $rows = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je',      'je.id',  '=', 'jel.journal_entry_id')
                ->join('accounting_accounts as aa',  'aa.id',  '=', 'jel.account_id')
                ->join('accounting_accounts as par', 'par.id', '=', 'aa.parent_id')
                ->where('je.status', 'aprobado')
                ->where('aa.company_id', $companyId)
                ->whereIn('aa.type', ['activo', 'pasivo', 'patrimonio'])
                ->where('aa.level', 3)
                ->whereYear('je.entry_date', $year)
                ->whereRaw($this->monthExpr('je.entry_date') . ' <= ?', [$month])
                ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature', 'aa.parent_id', 'par.code', 'par.name')
                ->select([
                    'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature', 'aa.parent_id',
                    'par.code as section_code', 'par.name as section_name',
                    DB::raw('SUM(jel.debit)  as total_debit'),
                    DB::raw('SUM(jel.credit) as total_credit'),
                ])
                ->orderBy('aa.code')
                ->get()
                ->map(function ($r) {
                    $debe  = (float) $r->total_debit;
                    $haber = (float) $r->total_credit;
                    $saldo = $r->nature === 'deudora' ? $debe - $haber : $haber - $debe;
                    return (object) array_merge((array) $r, [
                        'total_debit'  => $debe,
                        'total_credit' => $haber,
                        'saldo'        => $saldo,
                    ]);
                });

            $bySection = $rows->groupBy('section_code');

            return compact('rows', 'bySection', 'sections');
        });
    }

    public function estadoResultados(int $companyId, int $year, int $month, bool $acumulado): array
    {
        $key = $this->key('estado_resultados', $companyId, compact('year', 'month', 'acumulado'));
        $ttl = $this->ttl($year, $month);

        return Cache::remember($key, $ttl, function () use ($companyId, $year, $month, $acumulado) {
            $sections = AccountingAccount::forCompany($companyId)
                ->whereIn('type', ['ingreso', 'gasto'])
                ->where('level', 2)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']);

            $rows = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je',      'je.id',  '=', 'jel.journal_entry_id')
                ->join('accounting_accounts as aa',  'aa.id',  '=', 'jel.account_id')
                ->join('accounting_accounts as par', 'par.id', '=', 'aa.parent_id')
                ->where('je.status', 'aprobado')
                ->where('aa.company_id', $companyId)
                ->whereIn('aa.type', ['ingreso', 'gasto'])
                ->where('aa.level', 3)
                ->whereYear('je.entry_date', $year)
                ->when($acumulado,
                    fn ($q) => $q->whereRaw($this->monthExpr('je.entry_date') . ' <= ?', [$month]),
                    fn ($q) => $q->whereMonth('je.entry_date', $month)
                )
                ->groupBy('aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature', 'aa.parent_id', 'par.code', 'par.name')
                ->select([
                    'aa.id', 'aa.code', 'aa.name', 'aa.type', 'aa.nature', 'aa.parent_id',
                    'par.code as section_code', 'par.name as section_name',
                    DB::raw('SUM(jel.debit)  as total_debit'),
                    DB::raw('SUM(jel.credit) as total_credit'),
                ])
                ->orderBy('aa.code')
                ->get()
                ->map(function ($r) {
                    $debe  = (float) $r->total_debit;
                    $haber = (float) $r->total_credit;
                    $saldo = $r->nature === 'deudora' ? $debe - $haber : $haber - $debe;
                    return (object) array_merge((array) $r, [
                        'total_debit'  => $debe,
                        'total_credit' => $haber,
                        'saldo'        => $saldo,
                    ]);
                });

            $bySection     = $rows->groupBy('section_code');
            $totalIngresos = $rows->where('type', 'ingreso')->sum('saldo');
            $totalGastos   = $rows->where('type', 'gasto')->sum('saldo');
            $utilidad      = $totalIngresos - $totalGastos;

            return compact('rows', 'bySection', 'sections', 'totalIngresos', 'totalGastos', 'utilidad');
        });
    }
}
