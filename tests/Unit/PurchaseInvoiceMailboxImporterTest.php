<?php

namespace Tests\Unit;

use App\Models\PurchaseInvoice;
use App\Models\BillingSetting;
use App\Models\Supplier;
use App\Services\PurchaseInvoiceMailboxImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceMailboxImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_invoice_for_a_different_receptor_without_creating_records(): void
    {
        BillingSetting::put('emisor', ['nit' => '0614-010101-101-1']);

        $importer = new PurchaseInvoiceMailboxImporter();
        $reflection = new \ReflectionClass($importer);
        $taxIdMethod = $reflection->getMethod('configuredTaxIdentifier');
        $taxIdMethod->setAccessible(true);
        $configuredTaxId = $taxIdMethod->invoke($importer);

        $data = [
            'identificacion' => ['numeroControl' => 'DTE-OTRO-001'],
            'emisor' => ['nombre' => 'Proveedor ajeno'],
            'receptor' => ['tipoDocumento' => '36', 'numDocumento' => '0614-020202-202-2'],
        ];
        $summary = ['details' => []];
        $arguments = [json_encode($data), 'factura.json', 'UID-1', $configuredTaxId, &$summary];
        $method = $reflection->getMethod('importJson');
        $method->setAccessible(true);

        $result = $method->invokeArgs($importer, $arguments);

        $this->assertSame('filtered', $result);
        $this->assertSame('FILTRADA', $summary['details'][0]['status']);
        $this->assertSame(0, Supplier::count());
        $this->assertSame(0, PurchaseInvoice::count());
    }

    public function test_it_imports_invoice_when_receptor_matches_configured_tax_id(): void
    {
        BillingSetting::put('emisor', ['nit' => '0614-010101-101-1']);

        $importer = new PurchaseInvoiceMailboxImporter();
        $reflection = new \ReflectionClass($importer);
        $taxIdMethod = $reflection->getMethod('configuredTaxIdentifier');
        $taxIdMethod->setAccessible(true);
        $configuredTaxId = $taxIdMethod->invoke($importer);

        $data = [
            'identificacion' => [
                'numeroControl' => 'DTE-MATCH-001',
                'tipoDte' => '03',
                'fecEmi' => '2026-09-15',
            ],
            'emisor' => [
                'nombre' => 'Proveedor válido',
                'nit' => '0614-020202-202-2',
            ],
            'receptor' => [
                'tipoDocumento' => '36',
                'numDocumento' => '06140101011011',
            ],
            'resumen' => [
                'subTotal' => 100,
                'totalIva' => 13,
                'montoTotalOperacion' => 113,
            ],
        ];
        $summary = ['details' => []];
        $arguments = [json_encode($data), 'factura.json', 'UID-2', $configuredTaxId, &$summary];
        $method = $reflection->getMethod('importJson');
        $method->setAccessible(true);

        $result = $method->invokeArgs($importer, $arguments);

        $this->assertSame('imported', $result);
        $this->assertSame(1, Supplier::count());
        $this->assertSame(1, PurchaseInvoice::count());
    }

    public function test_it_does_not_import_the_same_generation_code_for_another_supplier(): void
    {
        BillingSetting::put('emisor', ['nit' => '0614-010101-101-1']);
        $importer = new PurchaseInvoiceMailboxImporter();
        $reflection = new \ReflectionClass($importer);
        $taxIdMethod = $reflection->getMethod('configuredTaxIdentifier');
        $taxIdMethod->setAccessible(true);
        $configuredTaxId = $taxIdMethod->invoke($importer);
        $method = $reflection->getMethod('importJson');
        $method->setAccessible(true);
        $generationCode = '12345678-1234-1234-1234-123456789ABC';

        $import = function (string $supplierName, string $controlNumber, string $uid) use ($method, $importer, $configuredTaxId, $generationCode): string {
            $summary = ['details' => []];
            $data = [
                'identificacion' => [
                    'numeroControl' => $controlNumber,
                    'codigoGeneracion' => $generationCode,
                    'tipoDte' => '03',
                    'fecEmi' => '2026-09-15',
                ],
                'emisor' => ['nombre' => $supplierName, 'nit' => $supplierName],
                'receptor' => ['tipoDocumento' => '36', 'numDocumento' => '06140101011011'],
                'resumen' => ['subTotal' => 10, 'totalIva' => 1.3, 'montoTotalOperacion' => 11.3],
            ];
            $arguments = [json_encode($data), 'factura.json', $uid, $configuredTaxId, &$summary];
            return $method->invokeArgs($importer, $arguments);
        };

        $this->assertSame('imported', $import('Proveedor A', 'DTE-A-001', 'UID-A'));
        $this->assertSame('duplicates', $import('Proveedor B', 'DTE-B-001', 'UID-B'));
        $this->assertSame(1, Supplier::count());
        $this->assertSame(1, PurchaseInvoice::count());
    }

    public function test_it_finds_json_inside_an_attached_forwarded_email_with_pdf(): void
    {
        $json = json_encode([
            'identificacion' => ['numeroControl' => 'DTE-TEST-001'],
            'emisor' => ['nombre' => 'Proveedor de prueba'],
        ], JSON_UNESCAPED_UNICODE);
        $nestedEmail = implode("\r\n", [
            'From: proveedor@example.test',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="inner-boundary"',
            '',
            '--inner-boundary',
            'Content-Type: application/pdf; name="factura.pdf"',
            'Content-Disposition: attachment; filename="factura.pdf"',
            'Content-Transfer-Encoding: base64',
            '',
            base64_encode('%PDF-1.4 test'),
            '--inner-boundary',
            'Content-Type: application/json; name="factura.json"',
            'Content-Disposition: attachment; filename="factura.json"',
            'Content-Transfer-Encoding: base64',
            '',
            base64_encode($json),
            '--inner-boundary--',
            '',
        ]);
        $message = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="outer-boundary"',
            '',
            '--outer-boundary',
            'Content-Type: text/plain; charset=utf-8',
            '',
            'Adjunto factura y correo reenviado.',
            '--outer-boundary',
            'Content-Type: message/rfc822; name="correo-reenviado.eml"',
            'Content-Disposition: attachment; filename="correo-reenviado.eml"',
            '',
            $nestedEmail,
            '--outer-boundary--',
            '',
        ]);

        $importer = new PurchaseInvoiceMailboxImporter();
        $method = new \ReflectionMethod($importer, 'jsonAttachments');
        $method->setAccessible(true);
        $attachments = $method->invoke($importer, $message);

        $this->assertCount(1, $attachments);
        $this->assertSame('factura.json', $attachments[0]['filename']);
        $this->assertSame($json, $attachments[0]['content']);
    }

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
        $this->assertFalse($importer->hasDuplicateInvoice(null, 'ABC-002'));
    }
}
