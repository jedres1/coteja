<?php

namespace Tests\Feature;

use App\Models\BillingInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingInvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function sentInvoice(array $overrides = []): BillingInvoice
    {
        return BillingInvoice::factory()->create(array_merge([
            'status'         => 'ENVIADO',
            'accepted'       => true,
            'total'          => 113.00,
            'amount_paid'    => 0,
            'payment_status' => 'pendiente',
        ], $overrides));
    }

    private function pay(BillingInvoice $invoice, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('admin.factura-sv.facturas.pagar', $invoice), $data);
    }

    // ── registerPayment ────────────────────────────────────────────────────────

    public function test_admin_can_register_partial_payment(): void
    {
        $invoice = $this->sentInvoice();

        $this->pay($invoice, ['amount' => 50.00, 'method' => 'efectivo'])
            ->assertSessionHasNoErrors();

        $this->assertSame('parcial', $invoice->fresh()->payment_status);
        $this->assertEquals(50.00, (float) $invoice->fresh()->amount_paid);
    }

    public function test_admin_can_register_full_payment(): void
    {
        $invoice = $this->sentInvoice();

        $this->pay($invoice, ['amount' => 113.00])
            ->assertSessionHasNoErrors();

        $this->assertSame('pagado', $invoice->fresh()->payment_status);
        $this->assertEquals(113.00, (float) $invoice->fresh()->amount_paid);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_payment_creates_billing_invoice_payment_record(): void
    {
        $invoice = $this->sentInvoice();

        $this->pay($invoice, ['amount' => 50.00, 'method' => 'transferencia', 'reference' => 'TRX-001']);

        $this->assertDatabaseHas('billing_invoice_payments', [
            'billing_invoice_id' => $invoice->id,
            'amount'             => 50.00,
            'method'             => 'transferencia',
            'reference'          => 'TRX-001',
        ]);
    }

    public function test_payment_exceeding_balance_is_rejected(): void
    {
        $invoice = $this->sentInvoice(['total' => 100.00]);

        $this->pay($invoice, ['amount' => 200.00])
            ->assertSessionHasErrors('amount');

        $this->assertSame('pendiente', $invoice->fresh()->payment_status);
    }

    public function test_already_paid_invoice_cannot_receive_payment(): void
    {
        $invoice = $this->sentInvoice([
            'total'          => 100.00,
            'amount_paid'    => 100.00,
            'payment_status' => 'pagado',
        ]);

        $this->pay($invoice, ['amount' => 10.00])
            ->assertSessionHasErrors('amount');
    }

    public function test_pending_invoice_cannot_receive_payment(): void
    {
        $invoice = $this->sentInvoice(['status' => 'PENDIENTE', 'accepted' => false]);

        $this->pay($invoice, ['amount' => 50.00])
            ->assertSessionHasErrors('amount');
    }

    public function test_second_partial_payment_accumulates_correctly(): void
    {
        $invoice = $this->sentInvoice(['total' => 200.00, 'amount_paid' => 80.00, 'payment_status' => 'parcial']);

        $this->pay($invoice, ['amount' => 120.00])
            ->assertSessionHasNoErrors();

        $this->assertSame('pagado', $invoice->fresh()->payment_status);
        $this->assertEquals(200.00, (float) $invoice->fresh()->amount_paid);
    }

    public function test_payment_amount_is_required(): void
    {
        $invoice = $this->sentInvoice();

        $this->pay($invoice, [])
            ->assertSessionHasErrors('amount');
    }
}
