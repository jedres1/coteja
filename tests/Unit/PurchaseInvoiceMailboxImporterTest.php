<?php

namespace Tests\Unit;

use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\PurchaseInvoiceMailboxImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceMailboxImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_duplicates_for_same_supplier_and_invoice_number(): void
    {
        $supplier = Supplier::create([
            'name' => 'Proveedor Test',
            'email' => 'proveedor@test.com',
            'phone' => '2222-2222',
            'document_type' => '36',
            'document_number' => '06140000000000',
            'nrc' => '123456-7',
            'trade_name' => 'Proveedor Test',
            'business_activity' => '62010',
            'activity_description' => 'Venta',
            'address_department' => '06',
            'address_municipality' => '0601',
            'address' => 'Dirección de prueba',
            'billing_email' => 'proveedor@test.com',
            'billing_phone' => '2222-2222',
            'status' => 'active',
        ]);

        PurchaseInvoice::create([
            'supplier_id' => $supplier->id,
            'document_type' => '03',
            'invoice_number' => 'ABC-001',
            'purchase_date' => '2026-07-15',
            'subtotal' => 100,
            'iva' => 13,
            'total' => 113,
            'payment_method' => '01',
            'payment_status' => 'pending',
            'status' => 'registered',
            'notes' => 'Factura existente',
        ]);

        $importer = new PurchaseInvoiceMailboxImporter;

        $this->assertTrue($importer->hasDuplicateInvoice($supplier->id, 'ABC-001'));
        $this->assertFalse($importer->hasDuplicateInvoice($supplier->id, 'ABC-002'));
    }
}
