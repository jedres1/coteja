<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingPeriod;
use App\Models\AccountingYearPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AccountingReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountingReportService $service;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->service = app(AccountingReportService::class);
        $this->company = Company::factory()->create();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function account(string $code, string $type, string $nature, ?int $parentId = null, int $level = 1): AccountingAccount
    {
        return AccountingAccount::create([
            'company_id' => $this->company->id,
            'code'       => $code,
            'name'       => "Cuenta {$code}",
            'type'       => $type,
            'nature'     => $nature,
            'parent_id'  => $parentId,
            'level'      => $level,
            'is_active'  => true,
        ]);
    }

    private function openPeriod(int $year, int $month): AccountingPeriod
    {
        AccountingYearPeriod::firstOrCreate(
            ['year' => $year],
            ['name' => "Ejercicio {$year}", 'status' => 'abierto', 'opened_at' => now()]
        );

        return AccountingPeriod::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['name' => AccountingPeriod::labelFor($year, $month), 'status' => 'abierto', 'opened_at' => now()]
        );
    }

    private function closePeriod(int $year, int $month): void
    {
        AccountingPeriod::where('year', $year)->where('month', $month)
            ->update(['status' => 'cerrado', 'closed_at' => now()]);
    }

    private function approvedEntry(string $date, array $lines): JournalEntry
    {
        $entry = JournalEntry::create([
            'company_id'   => $this->company->id,
            'entry_number' => 'AS-TEST-' . uniqid(),
            'entry_date'   => $date,
            'description'  => 'Test entry',
            'status'       => 'aprobado',
        ]);

        foreach ($lines as [$accountId, $debit, $credit]) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $accountId,
                'debit'            => $debit,
                'credit'           => $credit,
                'sort_order'       => 0,
            ]);
        }

        return $entry;
    }

    // ── saldos ─────────────────────────────────────────────────────────────

    public function test_saldos_returns_correct_debit_and_credit_totals(): void
    {
        $cash    = $this->account('1101', 'activo',  'deudora');
        $revenue = $this->account('4101', 'ingreso', 'acreedora');

        $this->approvedEntry('2026-10-05', [
            [$cash->id,    500, 0],
            [$revenue->id, 0, 500],
        ]);

        $result = $this->service->saldos($this->company->id, 2026, 10, null, false);

        $balances = collect($result['balances']);
        $cashRow    = $balances->firstWhere('id', $cash->id);
        $revenueRow = $balances->firstWhere('id', $revenue->id);

        $this->assertNotNull($cashRow);
        $this->assertEquals(500, $cashRow->total_debit);
        $this->assertEquals(0,   $cashRow->total_credit);
        $this->assertEquals(500, $cashRow->saldo); // deudora: debe - haber

        $this->assertNotNull($revenueRow);
        $this->assertEquals(0,   $revenueRow->total_debit);
        $this->assertEquals(500, $revenueRow->total_credit);
        $this->assertEquals(500, $revenueRow->saldo); // acreedora: haber - debe
    }

    public function test_saldos_acumulado_includes_prior_months(): void
    {
        $cash = $this->account('1101', 'activo', 'deudora');
        $equity = $this->account('3101', 'patrimonio', 'acreedora');

        // January entry
        $this->approvedEntry('2026-01-15', [[$cash->id, 200, 0], [$equity->id, 0, 200]]);
        // October entry
        $this->approvedEntry('2026-10-10', [[$cash->id, 300, 0], [$equity->id, 0, 300]]);

        $monthly     = $this->service->saldos($this->company->id, 2026, 10, null, false);
        $acumulado   = $this->service->saldos($this->company->id, 2026, 10, null, true);

        $monthlyDebit    = collect($monthly['balances'])->firstWhere('id', $cash->id)?->total_debit;
        $acumuladoDebit  = collect($acumulado['balances'])->firstWhere('id', $cash->id)?->total_debit;

        $this->assertEquals(300, $monthlyDebit);
        $this->assertEquals(500, $acumuladoDebit);
    }

    public function test_saldos_excludes_draft_entries(): void
    {
        $cash = $this->account('1101', 'activo', 'deudora');
        $equity = $this->account('3101', 'patrimonio', 'acreedora');

        $entry = JournalEntry::create([
            'company_id'   => $this->company->id,
            'entry_number' => 'AS-DRAFT-001',
            'entry_date'   => '2026-10-05',
            'description'  => 'Draft',
            'status'       => 'borrador',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $cash->id, 'debit' => 1000, 'credit' => 0, 'sort_order' => 0]);
        JournalEntryLine::create(['journal_entry_id' => $entry->id, 'account_id' => $equity->id, 'debit' => 0, 'credit' => 1000, 'sort_order' => 1]);

        $result = $this->service->saldos($this->company->id, 2026, 10, null, false);

        $this->assertEmpty(collect($result['balances'])->firstWhere('id', $cash->id));
    }

    public function test_saldos_isolates_by_company(): void
    {
        $otherCompany = Company::factory()->create();
        $cashOwn   = $this->account('1101', 'activo', 'deudora');
        $cashOther = AccountingAccount::create([
            'company_id' => $otherCompany->id,
            'code' => '1101', 'name' => 'Caja otra', 'type' => 'activo', 'nature' => 'deudora', 'level' => 1, 'is_active' => true,
        ]);

        $equity = $this->account('3101', 'patrimonio', 'acreedora');

        $this->approvedEntry('2026-10-01', [[$cashOwn->id, 100, 0], [$equity->id, 0, 100]]);

        // entry for other company
        $otherEntry = JournalEntry::create([
            'company_id' => $otherCompany->id, 'entry_number' => 'AS-OTHER-001',
            'entry_date' => '2026-10-01', 'description' => 'Other', 'status' => 'aprobado',
        ]);
        JournalEntryLine::create(['journal_entry_id' => $otherEntry->id, 'account_id' => $cashOther->id, 'debit' => 9999, 'credit' => 0, 'sort_order' => 0]);

        $result = $this->service->saldos($this->company->id, 2026, 10, null, false);
        $cashRow = collect($result['balances'])->firstWhere('id', $cashOwn->id);

        $this->assertEquals(100, $cashRow->total_debit);
    }

    // ── balanza ────────────────────────────────────────────────────────────

    public function test_balanza_shows_leaf_accounts_only(): void
    {
        $parent = $this->account('11', 'activo', 'deudora', null, 1);
        $leaf   = $this->account('1101', 'activo', 'deudora', $parent->id, 2);
        $equity = $this->account('3101', 'patrimonio', 'acreedora', null, 1);

        $this->approvedEntry('2026-10-05', [[$leaf->id, 500, 0], [$equity->id, 0, 500]]);

        $result = $this->service->balanza($this->company->id, 2026, 10, false, '', false);
        $ids    = collect($result['rows'])->pluck('id');

        $this->assertTrue($ids->contains($leaf->id));
        $this->assertFalse($ids->contains($parent->id), 'Parent accounts should not appear in balanza');
    }

    public function test_balanza_totals_match_sum_of_rows(): void
    {
        $cash   = $this->account('1101', 'activo', 'deudora');
        $equity = $this->account('3101', 'patrimonio', 'acreedora');

        $this->approvedEntry('2026-10-10', [[$cash->id, 800, 0], [$equity->id, 0, 800]]);

        $result = $this->service->balanza($this->company->id, 2026, 10, false, '', false);

        $rowsDebe  = collect($result['rows'])->filter(fn ($r) => $r->tiene_movimiento)->sum('suma_debe');
        $rowsHaber = collect($result['rows'])->filter(fn ($r) => $r->tiene_movimiento)->sum('suma_haber');

        $this->assertEquals($result['totalSumaDebe'],  $rowsDebe);
        $this->assertEquals($result['totalSumaHaber'], $rowsHaber);
    }

    // ── invalidateCompany ──────────────────────────────────────────────────

    public function test_invalidate_company_causes_stale_cache_to_refresh(): void
    {
        $cash   = $this->account('1101', 'activo', 'deudora');
        $equity = $this->account('3101', 'patrimonio', 'acreedora');

        $this->approvedEntry('2026-10-01', [[$cash->id, 100, 0], [$equity->id, 0, 100]]);

        // Prime the cache
        $first = $this->service->saldos($this->company->id, 2026, 10, null, false);
        $this->assertEquals(100, collect($first['balances'])->firstWhere('id', $cash->id)?->total_debit);

        // Add a new entry — without invalidation cache would return stale data
        $this->approvedEntry('2026-10-15', [[$cash->id, 200, 0], [$equity->id, 0, 200]]);

        // Without invalidation: still stale
        $stale = $this->service->saldos($this->company->id, 2026, 10, null, false);
        $this->assertEquals(100, collect($stale['balances'])->firstWhere('id', $cash->id)?->total_debit);

        // Invalidate → fresh result
        $this->service->invalidateCompany($this->company->id);
        $fresh = $this->service->saldos($this->company->id, 2026, 10, null, false);
        $this->assertEquals(300, collect($fresh['balances'])->firstWhere('id', $cash->id)?->total_debit);
    }

    // ── balanceGeneral ─────────────────────────────────────────────────────

    public function test_balance_general_groups_by_section(): void
    {
        $section = $this->account('11', 'activo', 'deudora', null, 2);
        $leaf    = $this->account('1101', 'activo', 'deudora', $section->id, 3);
        $equity  = $this->account('3101', 'patrimonio', 'acreedora', null, 3);

        $this->approvedEntry('2026-10-05', [[$leaf->id, 400, 0], [$equity->id, 0, 400]]);

        $result = $this->service->balanceGeneral($this->company->id, 2026, 10);

        $this->assertArrayHasKey('rows', $result);
        $this->assertArrayHasKey('bySection', $result);
        $this->assertArrayHasKey('sections', $result);

        $leafRow = collect($result['rows'])->firstWhere('id', $leaf->id);
        $this->assertNotNull($leafRow);
        $this->assertEquals(400, $leafRow->saldo);
    }

    // ── estadoResultados ───────────────────────────────────────────────────

    public function test_estado_resultados_calculates_utilidad(): void
    {
        $section  = $this->account('41', 'ingreso', 'acreedora', null, 2);
        $revenue  = $this->account('4101', 'ingreso', 'acreedora', $section->id, 3);
        $gSection = $this->account('51', 'gasto', 'deudora', null, 2);
        $expense  = $this->account('5101', 'gasto', 'deudora', $gSection->id, 3);
        $cash     = $this->account('1101', 'activo', 'deudora');

        $this->approvedEntry('2026-10-05', [
            [$cash->id, 1000, 0],
            [$revenue->id, 0, 1000],
        ]);
        $this->approvedEntry('2026-10-10', [
            [$expense->id, 300, 0],
            [$cash->id, 0, 300],
        ]);

        $result = $this->service->estadoResultados($this->company->id, 2026, 10, false);

        $this->assertEquals(1000, $result['totalIngresos']);
        $this->assertEquals(300,  $result['totalGastos']);
        $this->assertEquals(700,  $result['utilidad']);
    }

    // ── availableYears ─────────────────────────────────────────────────────

    public function test_available_years_always_includes_current_year(): void
    {
        $years = $this->service->availableYears();
        $this->assertContains(now()->year, $years);
    }

    public function test_available_years_includes_years_with_periods(): void
    {
        $this->openPeriod(2025, 1);
        $this->openPeriod(2026, 10);

        $years = $this->service->availableYears();
        $this->assertContains(2025, $years);
        $this->assertContains(2026, $years);
    }

    // ── libroMayor ─────────────────────────────────────────────────────────

    public function test_libro_mayor_computes_running_balance(): void
    {
        $cash   = $this->account('1101', 'activo', 'deudora');
        $equity = $this->account('3101', 'patrimonio', 'acreedora');

        $this->approvedEntry('2026-10-01', [[$cash->id, 1000, 0], [$equity->id, 0, 1000]]);
        $this->approvedEntry('2026-10-15', [[$equity->id, 200, 0], [$cash->id, 0, 200]]);

        $result = $this->service->libroMayor($this->company->id, 2026, 10, null, false);
        $ledger = collect($result['ledger']);

        $cashLedger = $ledger->first(fn ($l) => $l->account->id === $cash->id);
        $this->assertNotNull($cashLedger);
        $this->assertEquals(800, $cashLedger->saldo_final); // 1000 - 200 = 800
        $this->assertEquals(1000, $cashLedger->total_debe);
        $this->assertEquals(200,  $cashLedger->total_haber);

        // Running balance on each line
        $lines = collect($cashLedger->lines);
        $this->assertEquals(1000, $lines->first()->saldo);
        $this->assertEquals(800,  $lines->last()->saldo);
    }
}
