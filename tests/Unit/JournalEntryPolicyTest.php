<?php

namespace Tests\Unit;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryPolicyTest extends TestCase
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

    private function customerUser(): User
    {
        return User::factory()->create(['role' => 'customer', 'is_active' => true]);
    }

    private function entry(string $status): JournalEntry
    {
        return JournalEntry::create([
            'entry_number' => 'AS-TEST-' . uniqid(),
            'entry_date'   => now()->toDateString(),
            'description'  => 'Asiento de prueba',
            'status'       => $status,
        ]);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_any_role_can_update_a_draft_entry(): void
    {
        $entry = $this->entry('borrador');

        $this->assertTrue($this->admin()->can('update', $entry));
        $this->assertTrue($this->consultant()->can('update', $entry));
        $this->assertTrue($this->customerUser()->can('update', $entry));
    }

    public function test_no_one_can_update_an_approved_entry(): void
    {
        $entry = $this->entry('aprobado');

        $this->assertFalse($this->admin()->can('update', $entry));
        $this->assertFalse($this->consultant()->can('update', $entry));
        $this->assertFalse($this->customerUser()->can('update', $entry));
    }

    public function test_no_one_can_update_an_annulled_entry(): void
    {
        $entry = $this->entry('anulado');

        $this->assertFalse($this->admin()->can('update', $entry));
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function test_any_role_can_delete_a_draft_entry(): void
    {
        $entry = $this->entry('borrador');

        $this->assertTrue($this->admin()->can('delete', $entry));
        $this->assertTrue($this->consultant()->can('delete', $entry));
        $this->assertTrue($this->customerUser()->can('delete', $entry));
    }

    public function test_no_one_can_delete_an_approved_entry(): void
    {
        $entry = $this->entry('aprobado');

        $this->assertFalse($this->admin()->can('delete', $entry));
        $this->assertFalse($this->consultant()->can('delete', $entry));
        $this->assertFalse($this->customerUser()->can('delete', $entry));
    }

    // ── approve ───────────────────────────────────────────────────────────────

    public function test_admin_can_approve_a_draft_entry(): void
    {
        $this->assertTrue($this->admin()->can('approve', $this->entry('borrador')));
    }

    public function test_consultant_can_approve_a_draft_entry(): void
    {
        $this->assertTrue($this->consultant()->can('approve', $this->entry('borrador')));
    }

    public function test_customer_user_cannot_approve_an_entry(): void
    {
        $this->assertFalse($this->customerUser()->can('approve', $this->entry('borrador')));
    }

    public function test_admin_cannot_approve_an_already_approved_entry(): void
    {
        $this->assertFalse($this->admin()->can('approve', $this->entry('aprobado')));
    }

    // ── annul ─────────────────────────────────────────────────────────────────

    public function test_admin_can_annul_an_approved_entry(): void
    {
        $this->assertTrue($this->admin()->can('annul', $this->entry('aprobado')));
    }

    public function test_consultant_can_annul_an_approved_entry(): void
    {
        $this->assertTrue($this->consultant()->can('annul', $this->entry('aprobado')));
    }

    public function test_customer_user_cannot_annul_an_entry(): void
    {
        $this->assertFalse($this->customerUser()->can('annul', $this->entry('aprobado')));
    }

    public function test_admin_cannot_annul_a_draft_entry(): void
    {
        $this->assertFalse($this->admin()->can('annul', $this->entry('borrador')));
    }

    public function test_admin_cannot_annul_an_annulled_entry(): void
    {
        $this->assertFalse($this->admin()->can('annul', $this->entry('anulado')));
    }
}
