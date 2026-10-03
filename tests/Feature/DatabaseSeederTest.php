<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_loads_initial_data_and_accounting_accounts(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'admin@coteja.com']);
        $this->assertDatabaseHas('users', ['email' => 'cliente@demo.test']);
        $this->assertTrue(AccountingAccount::query()->where('code', '1')->exists());
    }
}
