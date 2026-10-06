<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyScopingTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): Supplier
    {
        static $seq = 0;
        $seq++;
        return Supplier::create([
            'name'                 => 'Proveedor ' . $seq,
            'document_type'        => '36',
            'document_number'      => '0614000000' . str_pad($seq, 4, '0', STR_PAD_LEFT),
            'address_department'   => '06',
            'address_municipality' => '0601',
            'address'              => 'Dirección Test',
        ]);
    }

    private function customer(string $label): Customer
    {
        static $seq = 0;
        $seq++;
        return Customer::create([
            'name'                 => 'Cliente ' . $label,
            'email'                => 'cliente' . $seq . '@test.com',
            'document_type'        => '36',
            'document_number'      => '0614' . str_pad($seq, 10, '0', STR_PAD_LEFT),
            'address_department'   => '06',
            'address_municipality' => '0601',
            'address'              => 'Dirección',
            'preferred_dte_type'   => '01',
            'status'               => 'active',
        ]);
    }

    private function company(Customer $customer): Company
    {
        static $seq = 0;
        $seq++;
        return Company::create([
            'customer_id'   => $customer->id,
            'business_name' => 'Empresa de ' . $customer->name,
            'nit_dui'       => '0614' . str_pad($seq * 100, 10, '0', STR_PAD_LEFT),
        ]);
    }

    private function invoice(Supplier $supplier, Customer $customer): PurchaseInvoice
    {
        return PurchaseInvoice::create([
            'supplier_id'    => $supplier->id,
            'customer_id'    => $customer->id,
            'invoice_number' => 'INV-' . strtoupper($customer->name) . '-' . uniqid(),
            'purchase_date'  => now()->toDateString(),
            'subtotal'       => 100,
            'iva'            => 13,
            'total'          => 113,
            'payment_status' => 'pending',
            'status'         => 'registered',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function consultant(): User
    {
        return User::factory()->create(['role' => 'consultant', 'is_active' => true]);
    }

    public function test_admin_sees_invoices_from_all_customers(): void
    {
        $supplier  = $this->supplier();
        $customerA = $this->customer('A');
        $customerB = $this->customer('B');
        $invA      = $this->invoice($supplier, $customerA);
        $invB      = $this->invoice($supplier, $customerB);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.purchase-invoices.index'));

        $response->assertOk();
        $this->assertStringContainsString($invA->invoice_number, $response->content());
        $this->assertStringContainsString($invB->invoice_number, $response->content());
    }

    public function test_consultant_assigned_to_one_company_sees_only_its_invoices(): void
    {
        $supplier  = $this->supplier();
        $customerA = $this->customer('A');
        $customerB = $this->customer('B');
        $companyA  = $this->company($customerA);
        $invA      = $this->invoice($supplier, $customerA);
        $invB      = $this->invoice($supplier, $customerB);

        $consultant = $this->consultant();
        $companyA->users()->attach($consultant);

        $response = $this->actingAs($consultant)
            ->get(route('admin.purchase-invoices.index'));

        $response->assertOk();
        $this->assertStringContainsString($invA->invoice_number, $response->content());
        $this->assertStringNotContainsString($invB->invoice_number, $response->content());
    }

    public function test_consultant_with_no_company_assignment_sees_no_invoices(): void
    {
        $supplier  = $this->supplier();
        $customerA = $this->customer('A');
        $invA      = $this->invoice($supplier, $customerA);

        $response = $this->actingAs($this->consultant())
            ->get(route('admin.purchase-invoices.index'));

        $response->assertOk();
        $this->assertStringNotContainsString($invA->invoice_number, $response->content());
    }

    public function test_consultant_assigned_to_multiple_companies_sees_all_their_invoices(): void
    {
        $supplier  = $this->supplier();
        $customerA = $this->customer('A');
        $customerB = $this->customer('B');
        $customerC = $this->customer('C');
        $companyA  = $this->company($customerA);
        $companyB  = $this->company($customerB);
        $invA      = $this->invoice($supplier, $customerA);
        $invB      = $this->invoice($supplier, $customerB);
        $invC      = $this->invoice($supplier, $customerC);

        $consultant = $this->consultant();
        $companyA->users()->attach($consultant);
        $companyB->users()->attach($consultant);

        $response = $this->actingAs($consultant)
            ->get(route('admin.purchase-invoices.index'));

        $response->assertOk();
        $this->assertStringContainsString($invA->invoice_number, $response->content());
        $this->assertStringContainsString($invB->invoice_number, $response->content());
        $this->assertStringNotContainsString($invC->invoice_number, $response->content());
    }
}
