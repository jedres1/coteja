<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── billing_settings: cambiar PK a surrogate + agregar customer_id ──────
        // 1. Quitar PK en 'key'
        DB::statement('ALTER TABLE `billing_settings` DROP PRIMARY KEY');
        // 2. Agregar columna id autoincrement como nueva PK
        DB::statement('ALTER TABLE `billing_settings` ADD `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        // 3. Agregar customer_id nullable (FK a customers)
        DB::statement('ALTER TABLE `billing_settings` ADD `customer_id` BIGINT UNSIGNED NULL AFTER `id`');
        DB::statement('ALTER TABLE `billing_settings` ADD CONSTRAINT `fk_bs_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE');
        // 4. Índice único (key, customer_id) — NULLs son distintos en MySQL, el código usa updateOrCreate
        DB::statement('ALTER TABLE `billing_settings` ADD UNIQUE KEY `bs_key_customer_unique` (`key`(191), `customer_id`)');

        // ── billing_dte_correlatives: agregar customer_id + actualizar índice único ──
        Schema::table('billing_dte_correlatives', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')->constrained('customers')->nullOnDelete();
        });
        DB::statement('ALTER TABLE `billing_dte_correlatives` DROP INDEX `billing_dte_correlatives_unique`');
        DB::statement('ALTER TABLE `billing_dte_correlatives` ADD UNIQUE KEY `billing_dte_correlatives_unique` (`document_type`, `year`, `establishment`, `point_of_sale`, `customer_id`)');

        // ── billing_invoices: agregar tenant_customer_id (el emisor/empresa) ───
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->foreignId('tenant_customer_id')->nullable()->after('id')->constrained('customers')->nullOnDelete();
            $table->index('tenant_customer_id', 'bi_tenant_idx');
        });

        // Migración de datos: asignar facturas existentes a la empresa del usuario que las creó
        DB::statement('
            UPDATE `billing_invoices` bi
            INNER JOIN `users` u ON u.id = bi.created_by AND u.customer_id IS NOT NULL
            SET bi.tenant_customer_id = u.customer_id
            WHERE bi.tenant_customer_id IS NULL
        ');
    }

    public function down(): void
    {
        // billing_invoices
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropIndex('bi_tenant_idx');
            $table->dropForeign(['tenant_customer_id']);
            $table->dropColumn('tenant_customer_id');
        });

        // billing_dte_correlatives
        DB::statement('ALTER TABLE `billing_dte_correlatives` DROP INDEX `billing_dte_correlatives_unique`');
        DB::statement('ALTER TABLE `billing_dte_correlatives` ADD UNIQUE KEY `billing_dte_correlatives_unique` (`document_type`, `year`, `establishment`, `point_of_sale`)');
        Schema::table('billing_dte_correlatives', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        // billing_settings: restaurar PK original en 'key'
        DB::statement('ALTER TABLE `billing_settings` DROP FOREIGN KEY `fk_bs_customer`');
        DB::statement('ALTER TABLE `billing_settings` DROP INDEX `bs_key_customer_unique`');
        DB::statement('ALTER TABLE `billing_settings` DROP COLUMN `customer_id`');
        DB::statement('ALTER TABLE `billing_settings` DROP PRIMARY KEY');
        DB::statement('ALTER TABLE `billing_settings` DROP COLUMN `id`');
        DB::statement('ALTER TABLE `billing_settings` ADD PRIMARY KEY (`key`(191))');
    }
};
