<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('controls_inventory')->default(false);
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('address');
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::table('billing_products', function (Blueprint $table) {
            $table->foreignId('product_type_id')->nullable()->after('id')
                ->constrained('product_types')->nullOnDelete();
        });

        Schema::create('billing_product_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unique(['billing_product_id', 'warehouse_id']);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('billing_products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('type', ['entry', 'exit']);
            $table->decimal('quantity', 12, 2);
            $table->string('document_type', 30)->default('manual'); // billing | purchase | manual
            $table->string('document_number', 255)->nullable();
            $table->foreignId('billing_invoice_id')->nullable()
                ->constrained('billing_invoices')->nullOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()
                ->constrained('purchase_invoices')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Tipos de producto por defecto
        DB::table('product_types')->insert([
            ['name' => 'Bien Terminado', 'controls_inventory' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Servicio',       'controls_inventory' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('billing_product_warehouse');
        Schema::table('billing_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_type_id');
        });
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('product_types');
    }
};
