<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('accounting_packages')->insertOrIgnore([
            [
                'code' => 'CXC',
                'name' => 'Cuentas por cobrar',
                'description' => 'Paquete para asientos manuales de cuentas por cobrar a clientes.',
                'type' => 'manual',
                'last_correlative' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'CXP',
                'name' => 'Cuentas por pagar',
                'description' => 'Paquete para asientos manuales de cuentas por pagar a proveedores.',
                'type' => 'manual',
                'last_correlative' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('accounting_packages')
            ->whereIn('code', ['CXC', 'CXP'])
            ->where('last_correlative', 0)
            ->delete();
    }
};