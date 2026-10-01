<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Clientes ──────────────────────────────────────────────────────────
        DB::table('customers')->upsert([
            [
                'id'                   => 1,
                'name'                 => 'Cliente Demo',
                'email'                => 'cliente@demo.test',
                'phone'                => '0000-0000',
                'document_type'        => '13',
                'document_number'      => null,
                'nrc'                  => null,
                'trade_name'           => null,
                'business_activity'    => null,
                'activity_description' => null,
                'address_department'   => null,
                'address_municipality' => null,
                'address'              => null,
                'preferred_dte_type'   => '01',
                'billing_email'        => null,
                'billing_phone'        => null,
                'status'               => 'active',
                'notes'                => null,
                'created_at'           => '2026-05-21 03:50:13',
                'updated_at'           => '2026-05-21 03:50:13',
            ],
            [
                'id'                   => 2,
                'name'                 => 'José Gerardo Jandres Argueta',
                'email'                => 'jjandres09cost5@gmail.com',
                'phone'                => '70398265',
                'document_type'        => '13',
                'document_number'      => '046463505',
                'nrc'                  => '39030',
                'trade_name'           => 'Jandres',
                'business_activity'    => null,
                'activity_description' => null,
                'address_department'   => '06',
                'address_municipality' => '0602',
                'address'              => 'Colonia Trinidad Calle Principal Casa No.7 Contiguo Al Puente San Bartolo',
                'preferred_dte_type'   => '01',
                'billing_email'        => 'jjandres09cost5@gmail.com',
                'billing_phone'        => '70398265',
                'status'               => 'active',
                'notes'                => null,
                'created_at'           => '2026-05-30 02:39:30',
                'updated_at'           => '2026-05-30 02:39:30',
            ],
        ], ['id'], [
            'name', 'email', 'phone', 'document_type', 'document_number', 'nrc',
            'trade_name', 'address_department', 'address_municipality', 'address',
            'preferred_dte_type', 'billing_email', 'billing_phone', 'status',
        ]);

        // ── Usuarios ──────────────────────────────────────────────────────────
        // Las contraseñas están almacenadas como bcrypt (cost 12).
        // Para cambiarlas use: php artisan tinker → Hash::make('nueva_clave')
        DB::table('users')->upsert([
            [
                'id'                => 1,
                'customer_id'       => 2,
                'name'              => 'Administrador',
                'email'             => 'jandres.gerardo@outlook.com',
                // contraseña original (bcrypt hash — no es texto plano)
                'password'          => '$2y$12$5P2aQOZxIjRJfRnOTZv4YO5t/m9uel3xkwfh6Amv4TqkPNM.epnVW',
                'role'              => 'customer',
                'is_active'         => 1,
                'module_accesses'   => json_encode(['billing', 'purchases']),
                'email_verified_at' => null,
                'created_at'        => '2026-05-21 03:50:13',
                'updated_at'        => '2026-09-26 11:13:34',
            ],
            [
                'id'                => 2,
                'customer_id'       => 1,
                'name'              => 'Cliente Demo',
                'email'             => 'cliente@demo.com',
                'password'          => '$2y$12$VD9JTdAV54GA.I4CRiu4TebdSnwyRlY5nx2qrr6zMOil/AJuXNcpq',
                'role'              => 'customer',
                'is_active'         => 1,
                'module_accesses'   => json_encode(['billing']),
                'email_verified_at' => null,
                'created_at'        => '2026-05-21 03:50:13',
                'updated_at'        => '2026-09-26 09:26:04',
            ],
            [
                'id'                => 4,
                'customer_id'       => null,
                'name'              => 'admin',
                'email'             => 'admin@coteja.com',
                'password'          => '$2y$12$LWnmSJl7oU6YZ6DvOT/zh.rthgwuSr9DWgl6KYTwDQCj.OfMHDojK',
                'role'              => 'admin',
                'is_active'         => 1,
                'module_accesses'   => json_encode([]),
                'email_verified_at' => null,
                'created_at'        => '2026-09-26 09:36:49',
                'updated_at'        => '2026-09-26 10:55:07',
            ],
        ], ['id'], [
            'customer_id', 'name', 'email', 'password', 'role',
            'is_active', 'module_accesses',
        ]);
    }
}
