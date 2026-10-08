<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        // ── billing_settings ──────────────────────────────────────────────────
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `billing_settings` DROP PRIMARY KEY');
            DB::statement('ALTER TABLE `billing_settings` ADD `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
            DB::statement('ALTER TABLE `billing_settings` ADD `customer_id` BIGINT UNSIGNED NULL AFTER `id`');
            DB::statement('ALTER TABLE `billing_settings` ADD CONSTRAINT `fk_bs_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE');
            DB::statement('ALTER TABLE `billing_settings` ADD UNIQUE KEY `bs_key_customer_unique` (`key`(191), `customer_id`)');
        } else {
            // SQLite: recreate the table (ALTER TABLE can't change PK in SQLite)
            $existing = DB::table('billing_settings')->get(['key', 'value']);
            Schema::drop('billing_settings');
            Schema::create('billing_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('key');
                $table->longText('value')->nullable();
                $table->timestamps();
                $table->unique(['key', 'customer_id'], 'bs_key_customer_unique');
            });
            foreach ($existing as $row) {
                DB::table('billing_settings')->insert(['key' => $row->key, 'value' => $row->value, 'customer_id' => null]);
            }
        }

        // ── billing_dte_correlatives ──────────────────────────────────────────
        Schema::table('billing_dte_correlatives', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')->constrained('customers')->nullOnDelete();
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `billing_dte_correlatives` DROP INDEX `billing_dte_correlatives_unique`');
            DB::statement('ALTER TABLE `billing_dte_correlatives` ADD UNIQUE KEY `billing_dte_correlatives_unique` (`document_type`, `year`, `establishment`, `point_of_sale`, `customer_id`)');
        } else {
            Schema::table('billing_dte_correlatives', function (Blueprint $table) {
                $table->dropUnique('billing_dte_correlatives_unique');
                $table->unique(
                    ['document_type', 'year', 'establishment', 'point_of_sale', 'customer_id'],
                    'billing_dte_correlatives_unique'
                );
            });
        }

        // ── billing_invoices ──────────────────────────────────────────────────
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->foreignId('tenant_customer_id')->nullable()->after('id')->constrained('customers')->nullOnDelete();
            $table->index('tenant_customer_id', 'bi_tenant_idx');
        });

        if ($driver === 'mysql') {
            DB::statement('
                UPDATE `billing_invoices` bi
                INNER JOIN `users` u ON u.id = bi.created_by AND u.customer_id IS NOT NULL
                SET bi.tenant_customer_id = u.customer_id
                WHERE bi.tenant_customer_id IS NULL
            ');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropIndex('bi_tenant_idx');
            $table->dropForeign(['tenant_customer_id']);
            $table->dropColumn('tenant_customer_id');
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `billing_dte_correlatives` DROP INDEX `billing_dte_correlatives_unique`');
            DB::statement('ALTER TABLE `billing_dte_correlatives` ADD UNIQUE KEY `billing_dte_correlatives_unique` (`document_type`, `year`, `establishment`, `point_of_sale`)');
        } else {
            Schema::table('billing_dte_correlatives', function (Blueprint $table) {
                $table->dropUnique('billing_dte_correlatives_unique');
                $table->unique(
                    ['document_type', 'year', 'establishment', 'point_of_sale'],
                    'billing_dte_correlatives_unique'
                );
            });
        }

        Schema::table('billing_dte_correlatives', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `billing_settings` DROP FOREIGN KEY `fk_bs_customer`');
            DB::statement('ALTER TABLE `billing_settings` DROP INDEX `bs_key_customer_unique`');
            DB::statement('ALTER TABLE `billing_settings` DROP COLUMN `customer_id`');
            DB::statement('ALTER TABLE `billing_settings` DROP PRIMARY KEY');
            DB::statement('ALTER TABLE `billing_settings` DROP COLUMN `id`');
            DB::statement('ALTER TABLE `billing_settings` ADD PRIMARY KEY (`key`(191))');
        } else {
            $existing = DB::table('billing_settings')->get(['key', 'value']);
            Schema::drop('billing_settings');
            Schema::create('billing_settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->longText('value')->nullable();
                $table->timestamps();
            });
            foreach ($existing as $row) {
                DB::table('billing_settings')->insert(['key' => $row->key, 'value' => $row->value]);
            }
        }
    }
};
