<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('type', ['automatico', 'manual'])->default('automatico');
            $table->foreignId('debit_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('secondary_debit_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('secondary_credit_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->unsignedBigInteger('last_correlative')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Paquetes por defecto
        DB::table('accounting_packages')->insert([
            ['code' => 'FA', 'name' => 'Facturación',          'description' => 'Generado por facturas electrónicas de venta.',    'type' => 'automatico', 'is_active' => true, 'last_correlative' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CP', 'name' => 'Compras',              'description' => 'Generado al aprobar facturas de compra.',          'type' => 'automatico', 'is_active' => true, 'last_correlative' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'IN', 'name' => 'Inventario',           'description' => 'Generado por movimientos manuales de inventario.', 'type' => 'automatico', 'is_active' => true, 'last_correlative' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CB', 'name' => 'Control Bancario',     'description' => 'Generado desde transacciones bancarias.',          'type' => 'automatico', 'is_active' => true, 'last_correlative' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CG', 'name' => 'Contabilidad General', 'description' => 'Partidas creadas directamente desde contabilidad.', 'type' => 'manual',     'is_active' => true, 'last_correlative' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_packages');
    }
};
