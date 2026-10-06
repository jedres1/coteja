<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\License;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function consultant(): User
    {
        return User::factory()->create(['role' => 'consultant', 'is_active' => true]);
    }

    private function makeCustomer(string $suffix = '01'): Customer
    {
        return Customer::create([
            'name'                 => 'Empresa Test ' . $suffix,
            'email'                => 'empresa' . $suffix . '@test.com',
            'document_type'        => '36',
            'document_number'      => '061400000000' . $suffix,
            'address_department'   => '06',
            'address_municipality' => '0601',
            'address'              => 'Calle Principal',
            'preferred_dte_type'   => '01',
            'status'               => 'active',
        ]);
    }

    private function validStorePayload(string $suffix = '99'): array
    {
        return [
            'name'                 => 'Cliente Nuevo',
            'email'                => 'nuevo' . $suffix . '@cliente.com',
            'document_type'        => '36',
            'document_number'      => '061400000000' . $suffix,
            'address_department'   => '06',
            'address_municipality' => '0601',
            'address'              => 'Calle Secundaria',
            'preferred_dte_type'   => '01',
            'status'               => 'active',
            'password'             => 'password123',
        ];
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_admin_can_list_customers(): void
    {
        $this->makeCustomer();

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index'))
            ->assertOk();
    }

    public function test_consultant_cannot_access_customer_list(): void
    {
        $this->actingAs($this->consultant())
            ->get(route('admin.customers.index'))
            ->assertForbidden();
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_customer_and_linked_user(): void
    {
        $payload = $this->validStorePayload();

        $this->actingAs($this->admin())
            ->post(route('admin.customers.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('customers', ['email' => $payload['email']]);
        $this->assertDatabaseHas('users', ['email' => $payload['email'], 'role' => 'customer']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.customers.store'), [])
            ->assertSessionHasErrors([
                'name', 'email', 'document_type', 'document_number',
                'address_department', 'address_municipality', 'address',
                'preferred_dte_type', 'status', 'password',
            ]);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        $this->makeCustomer('01');
        $payload = $this->validStorePayload();
        $payload['email'] = 'empresa01@test.com';

        $this->actingAs($this->admin())
            ->post(route('admin.customers.store'), $payload)
            ->assertSessionHasErrors(['email']);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_customer(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->admin())
            ->put(route('admin.customers.update', $customer), [
                'name'                 => 'Nombre Modificado',
                'email'                => $customer->email,
                'document_type'        => $customer->document_type,
                'document_number'      => $customer->document_number,
                'address_department'   => $customer->address_department,
                'address_municipality' => $customer->address_municipality,
                'address'              => $customer->address,
                'preferred_dte_type'   => '01',
                'status'               => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Nombre Modificado', $customer->fresh()->name);
    }

    // ── destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_soft_delete_customer_with_no_active_licenses(): void
    {
        $customer = $this->makeCustomer();
        $admin    = $this->admin();

        $this->assertTrue(
            $admin->can('delete', $customer),
            'CustomerPolicy::delete should allow an admin with no active licenses'
        );

        $this->actingAs($admin)
            ->from(route('admin.customers.index'))
            ->delete(route('admin.customers.destroy', $customer))
            ->assertRedirect();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_admin_cannot_delete_customer_with_active_license(): void
    {
        $customer = $this->makeCustomer();
        $company  = Company::create([
            'customer_id'   => $customer->id,
            'business_name' => 'Empresa Test',
            'nit_dui'       => '06140000000099',
        ]);
        $plan = Plan::create(['code' => 'BASIC', 'name' => 'Básico', 'monthly_price' => 0]);
        License::create([
            'customer_id' => $customer->id,
            'company_id'  => $company->id,
            'plan_id'     => $plan->id,
            'license_key' => 'ACTIVE-KEY-001',
            'status'      => 'active',
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.customers.index'))
            ->delete(route('admin.customers.destroy', $customer))
            ->assertForbidden();

        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_consultant_cannot_delete_customer(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->consultant())
            ->from(route('admin.customers.index'))
            ->delete(route('admin.customers.destroy', $customer))
            ->assertForbidden();

        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
    }

    // ── export ────────────────────────────────────────────────────────────────

    public function test_export_returns_csv_content_type(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.customers.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_export_includes_customer_rows(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.customers.export'));

        $response->assertOk();
        $this->assertStringContainsString($customer->email, $response->streamedContent());
    }
}
