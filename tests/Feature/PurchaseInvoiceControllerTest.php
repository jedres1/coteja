<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\AccountingPeriod;
use App\Models\AccountingYearPeriod;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function invoice(array $overrides = []): PurchaseInvoice
    {
        return PurchaseInvoice::factory()->create($overrides);
    }

    private function openAccountingPeriod(): void
    {
        $year  = now()->year;
        $month = now()->month;

        AccountingYearPeriod::firstOrCreate(
            ['year' => $year],
            ['name' => "Ejercicio contable {$year}", 'status' => 'abierto', 'opened_at' => now()],
        );

        AccountingPeriod::firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['name' => AccountingPeriod::labelFor($year, $month), 'status' => 'abierto', 'opened_at' => now()],
        );
    }

    private function configureAccountingPackage(string $code): void
    {
        $debit  = AccountingAccount::create(['code' => '5101', 'name' => 'Compras', 'type' => 'gasto', 'nature' => 'deudora']);
        $credit = AccountingAccount::create(['code' => '2101', 'name' => 'Proveedores', 'type' => 'pasivo', 'nature' => 'acreedora']);

        AccountingPackage::where('code', $code)->update([
            'debit_account_id'  => $debit->id,
            'credit_account_id' => $credit->id,
            'is_active'         => true,
        ]);
    }

    // ── index ──────────────────────────────────────────────────────────────────

    public function test_admin_can_list_purchase_invoices(): void
    {
        PurchaseInvoice::factory()->approved()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.purchase-invoices.index'))
            ->assertOk();
    }

    // ── store ──────────────────────────────────────────────────────────────────

    public function test_admin_can_create_purchase_invoice(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.purchase-invoices.store'), [
                'supplier_id'    => $supplier->id,
                'document_type'  => '01',
                'invoice_number' => 'CP-TEST-001',
                'purchase_date'  => now()->toDateString(),
                'subtotal'       => 100,
                'iva'            => 13,
                'total'          => 113,
                'payment_status' => 'pending',
                'status'         => 'registered',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_invoices', ['invoice_number' => 'CP-TEST-001']);
    }

    // ── approve ────────────────────────────────────────────────────────────────

    public function test_admin_can_approve_extracted_invoice_with_open_period(): void
    {
        $this->openAccountingPeriod();
        $this->configureAccountingPackage('CP');

        $invoice = $this->invoice(['status' => 'extracted', 'purchase_date' => now()->toDateString()]);

        $this->actingAs($this->admin())
            ->post(route('admin.purchase-invoices.approve', $invoice))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('approved', $invoice->fresh()->status);
    }

    public function test_approve_fails_when_accounting_period_is_closed(): void
    {
        $invoice = $this->invoice(['status' => 'extracted', 'purchase_date' => now()->toDateString()]);

        $this->actingAs($this->admin())
            ->post(route('admin.purchase-invoices.approve', $invoice))
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertSame('extracted', $invoice->fresh()->status);
    }

    public function test_approve_creates_journal_entry(): void
    {
        $this->openAccountingPeriod();
        $this->configureAccountingPackage('CP');

        $invoice = $this->invoice(['status' => 'extracted', 'purchase_date' => now()->toDateString()]);

        $this->actingAs($this->admin())
            ->post(route('admin.purchase-invoices.approve', $invoice));

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'purchase',
            'source_id'   => $invoice->id,
        ]);
    }

    // ── reject ─────────────────────────────────────────────────────────────────

    public function test_admin_can_reject_extracted_invoice(): void
    {
        $invoice = $this->invoice(['status' => 'extracted']);

        $this->actingAs($this->admin())
            ->post(route('admin.purchase-invoices.reject', $invoice))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('rejected', $invoice->fresh()->status);
    }

    // ── approveBulk ────────────────────────────────────────────────────────────

    public function test_admin_can_bulk_approve_invoices(): void
    {
        $this->openAccountingPeriod();
        $this->configureAccountingPackage('CP');

        $invoices = PurchaseInvoice::factory()->count(3)
            ->state(['status' => 'extracted', 'purchase_date' => now()->toDateString()])
            ->create();

        $this->actingAs($this->admin())
            ->post(route('admin.purchase-invoices.approve-bulk'), [
                'ids' => $invoices->pluck('id')->all(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        foreach ($invoices as $invoice) {
            $this->assertSame('approved', $invoice->fresh()->status);
        }
    }

    public function test_bulk_approve_skips_invoices_with_closed_period(): void
    {
        $this->openAccountingPeriod();
        $this->configureAccountingPackage('CP');

        $openInvoice   = $this->invoice(['status' => 'extracted', 'purchase_date' => now()->toDateString()]);
        $closedInvoice = $this->invoice(['status' => 'extracted', 'purchase_date' => '2000-01-01']);

        $this->actingAs($this->admin())
            ->post(route('admin.purchase-invoices.approve-bulk'), [
                'ids' => [$openInvoice->id, $closedInvoice->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $openInvoice->fresh()->status);
        $this->assertSame('extracted', $closedInvoice->fresh()->status);
    }

    // ── rejectBulk ─────────────────────────────────────────────────────────────

    public function test_admin_can_bulk_reject_invoices(): void
    {
        $invoices = PurchaseInvoice::factory()->count(2)
            ->state(['status' => 'extracted'])
            ->create();

        $this->actingAs($this->admin())
            ->post(route('admin.purchase-invoices.reject-bulk'), [
                'ids' => $invoices->pluck('id')->all(),
            ])
            ->assertSessionHasNoErrors();

        foreach ($invoices as $invoice) {
            $this->assertSame('rejected', $invoice->fresh()->status);
        }
    }

    // ── destroy ────────────────────────────────────────────────────────────────

    public function test_admin_can_delete_extracted_invoice(): void
    {
        $invoice = $this->invoice(['status' => 'extracted']);

        $this->actingAs($this->admin())
            ->delete(route('admin.purchase-invoices.destroy', $invoice))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('purchase_invoices', ['id' => $invoice->id]);
    }

    public function test_non_admin_cannot_delete_invoice(): void
    {
        $consultant = User::factory()->create(['role' => 'consultant', 'is_active' => true]);
        $invoice    = $this->invoice(['status' => 'extracted']);

        $this->actingAs($consultant)
            ->delete(route('admin.purchase-invoices.destroy', $invoice))
            ->assertForbidden();

        $this->assertDatabaseHas('purchase_invoices', ['id' => $invoice->id, 'deleted_at' => null]);
    }
}
