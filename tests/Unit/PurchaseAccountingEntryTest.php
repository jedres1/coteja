<?php

namespace Tests\Unit;

use App\Models\AccountingAccount;
use App\Models\AccountingPackage;
use App\Models\CostCenter;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\Accounting\AccountingEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PurchaseAccountingEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_purchase_and_payable_entries_with_configured_accounts(): void
    {
        $purchaseDebit = $this->account('5101', 'Compras', 'gasto', 'deudora');
        $payable = $this->account('2101', 'Proveedores', 'pasivo', 'acreedora');
        $bank = $this->account('1101', 'Banco', 'activo', 'deudora');
        $costCenter = CostCenter::create([
            'code' => 'ADM',
            'name' => 'Administración',
        ]);

        AccountingPackage::where('code', 'CP')->update([
            'debit_account_id' => $purchaseDebit->id,
            'credit_account_id' => $payable->id,
            'cost_center_id' => $costCenter->id,
            'is_active' => true,
        ]);
        AccountingPackage::where('code', 'CXP')->update([
            'debit_account_id' => $payable->id,
            'credit_account_id' => $bank->id,
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => 'Proveedor de prueba',
            'document_type' => '36',
            'document_number' => '06140000000000',
            'address_department' => '06',
            'address_municipality' => '0601',
            'address' => 'Dirección de prueba',
        ]);
        $invoice = PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'CP-TEST-001',
            'purchase_date' => '2026-10-01',
            'subtotal' => 100,
            'iva' => 13,
            'total' => 113,
        ]);
        $service = new AccountingEntryService();

        $purchaseEntry = $service->createFromPurchase($invoice->load('supplier'));
        $this->assertNotNull($purchaseEntry);
        $this->assertSame('CP', $purchaseEntry->accountingPackage->code);
        $this->assertNull($service->createFromPurchase($invoice));

        $purchaseLines = $purchaseEntry->lines()->get();
        $this->assertCount(2, $purchaseLines);
        $this->assertSame($purchaseDebit->id, $purchaseLines[0]->account_id);
        $this->assertSame($costCenter->id, $purchaseLines[0]->cost_center_id);
        $this->assertEquals(113, $purchaseLines[0]->debit);
        $this->assertSame($payable->id, $purchaseLines[1]->account_id);
        $this->assertSame($costCenter->id, $purchaseLines[1]->cost_center_id);
        $this->assertEquals(113, $purchaseLines[1]->credit);

        $paymentEntry = $service->createFromPurchasePayment($invoice, null, '2026-10-04', 'TRX-001');
        $this->assertNotNull($paymentEntry);
        $this->assertSame('CXP', $paymentEntry->accountingPackage->code);
        $this->assertSame('2026-10-04', $paymentEntry->entry_date->toDateString());
        $this->assertSame('TRX-001', $paymentEntry->reference);
        $this->assertSame('purchase_payment', $paymentEntry->source_type);
        $this->assertCount(2, $paymentEntry->lines);
        $this->assertSame($payable->id, $paymentEntry->lines[0]->account_id);
        $this->assertEquals(113, $paymentEntry->lines[0]->debit);
        $this->assertSame($bank->id, $paymentEntry->lines[1]->account_id);
        $this->assertEquals(113, $paymentEntry->lines[1]->credit);
        $this->assertNull($service->createFromPurchasePayment($invoice));
    }

    public function test_it_rejects_purchase_approval_when_cp_accounts_are_not_configured(): void
    {
        AccountingPackage::where('code', 'CP')->update([
            'debit_account_id' => null,
            'credit_account_id' => null,
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => 'Proveedor de prueba',
            'document_type' => '36',
            'document_number' => '06140000000000',
            'address_department' => '06',
            'address_municipality' => '0601',
            'address' => 'Dirección de prueba',
        ]);
        $invoice = PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'CP-TEST-002',
            'purchase_date' => '2026-10-01',
            'subtotal' => 100,
            'iva' => 13,
            'total' => 113,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configure las cuentas de débito y crédito del paquete CP');

        (new AccountingEntryService())->createFromPurchase($invoice);
    }

    private function account(string $code, string $name, string $type, string $nature): AccountingAccount
    {
        return AccountingAccount::create(compact('code', 'name', 'type', 'nature'));
    }
}
