<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\BankAccount;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePaymentBankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_purchase_as_paid_requires_an_active_registered_bank_account(): void
    {
        $invoice = $this->invoice();
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)
            ->from(route('admin.purchase-invoices.accounts-payable'))
            ->post(route('admin.purchase-invoices.pay', $invoice), [
                'payment_status' => 'paid',
                'transaction_date' => '2026-10-04',
            ])
            ->assertRedirect(route('admin.purchase-invoices.accounts-payable'))
            ->assertSessionHasErrors('bank_account_id');

        $this->assertSame('pending', $invoice->fresh()->payment_status);
    }

    public function test_paid_purchase_payment_uses_the_selected_bank_account(): void
    {
        $payable = $this->account('2101', 'Proveedores', 'pasivo', 'acreedora');
        $bankLedger = $this->account('1101', 'Banco', 'activo', 'deudora');
        AccountingPackage::where('code', 'CXP')->update([
            'debit_account_id' => $payable->id,
            'credit_account_id' => $bankLedger->id,
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::create([
            'name' => 'Cuenta corriente',
            'bank_name' => 'Banco de prueba',
            'account_number' => '123456',
            'account_type' => 'corriente',
            'currency' => 'USD',
            'is_active' => true,
        ]);
        $invoice = $this->invoice();
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)
            ->post(route('admin.purchase-invoices.pay', $invoice), [
                'payment_status' => 'paid',
                'transaction_date' => '2026-10-04',
                'bank_account_id' => $bankAccount->id,
                'reference' => 'TRX-001',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('paid', $invoice->fresh()->payment_status);
        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => '2026-10-04 00:00:00',
            'amount' => 113,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'accounting_package_id' => AccountingPackage::where('code', 'CXP')->value('id'),
            'source_type' => 'purchase_payment',
            'source_id' => $invoice->id,
        ]);
    }

    private function invoice(): PurchaseInvoice
    {
        $supplier = Supplier::create([
            'name' => 'Proveedor de prueba',
            'document_type' => '36',
            'document_number' => '06140000000000',
            'address_department' => '06',
            'address_municipality' => '0601',
            'address' => 'Dirección de prueba',
        ]);

        return PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'CP-BANK-'.uniqid(),
            'purchase_date' => '2026-10-01',
            'subtotal' => 100,
            'iva' => 13,
            'total' => 113,
            'payment_status' => 'pending',
            'status' => 'approved',
        ]);
    }

    private function account(string $code, string $name, string $type, string $nature): AccountingAccount
    {
        return AccountingAccount::create(compact('code', 'name', 'type', 'nature'));
    }
}