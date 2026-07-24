<?php

namespace Database\Seeders;

use App\Models\AppRelease;
use App\Models\Company;
use App\Models\Customer;
use App\Models\License;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'jandres.gerardo@outlook.com'],
            [
                'name' => 'Administrador',
                'role' => 'admin',
                'is_active' => true,
                'password' => Hash::make(env('COTEJA_ADMIN_PASSWORD', 'change-me-admin-2026')),
            ]
        );

        $local = Plan::updateOrCreate(
            ['code' => 'local'],
            [
                'name' => 'Plan Local',
                'monthly_price' => 4.99,
                'annual_price' => 49.90,
                'implementation_fee' => 100,
                'additional_user_price' => 0,
                'included_users' => 1,
                'max_devices' => 1,
                'support_included' => false,
                'cloud_backup' => false,
                'unlimited_documents' => true,
                'is_active' => true,
            ]
        );

        Plan::updateOrCreate(
            ['code' => 'empresa'],
            [
                'name' => 'Plan Empresas',
                'monthly_price' => 19.99,
                'annual_price' => 199.90,
                'implementation_fee' => 150,
                'additional_user_price' => 2.99,
                'included_users' => 1,
                'max_devices' => 1,
                'support_included' => true,
                'cloud_backup' => true,
                'unlimited_documents' => true,
                'is_active' => true,
            ]
        );

        $customer = Customer::updateOrCreate(
            ['email' => 'cliente@demo.test'],
            ['name' => 'Cliente Demo', 'phone' => '0000-0000', 'status' => 'active']
        );

        User::updateOrCreate(
            ['email' => 'cliente@demo.test'],
            [
                'customer_id' => $customer->id,
                'name' => 'Cliente Demo',
                'role' => 'customer',
                'is_active' => true,
                'password' => Hash::make(env('COTEJA_DEMO_CUSTOMER_PASSWORD', 'change-me-customer-2026')),
            ]
        );

        $company = Company::updateOrCreate(
            ['nit_dui' => '00000000000000'],
            [
                'customer_id' => $customer->id,
                'business_name' => 'Empresa Demo',
                'trade_name' => 'Demo',
                'email' => 'cliente@demo.test',
                'environment' => 'production',
            ]
        );

        License::updateOrCreate(
            ['license_key' => 'COT-DEMO-0001'],
            [
                'customer_id' => $customer->id,
                'company_id' => $company->id,
                'plan_id' => $local->id,
                'status' => 'active',
                'starts_at' => now()->toDateString(),
                'expires_at' => now()->addYear()->toDateString(),
                'max_users' => 1,
                'max_devices' => 1,
                'grace_days' => 7,
            ]
        );

        AppRelease::updateOrCreate(
            ['platform' => 'windows', 'version' => '1.0.0'],
            [
                'filename' => 'Facturacion-Electron-Setup.exe',
                'download_url' => 'https://example.com/downloads/Facturacion-Electron-Setup.exe',
                'notes' => 'Reemplace esta URL por el instalador real.',
                'is_active' => true,
            ]
        );

        AppRelease::updateOrCreate(
            ['platform' => 'ios', 'version' => '1.0.0'],
            [
                'filename' => 'Facturacion-Electron-iOS.ipa',
                'download_url' => 'https://example.com/downloads/Facturacion-Electron-iOS.ipa',
                'notes' => 'Reemplace esta URL por el archivo real.',
                'is_active' => true,
            ]
        );
    }
}
