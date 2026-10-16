<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('controls_inventory');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('name');
            $table->string('contact_name', 100)->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('contact_name');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->after('name');
        });

        // Make employees.code nullable so it can be auto-generated post-insert
        DB::statement('ALTER TABLE employees MODIFY code VARCHAR(20) NULL');
    }

    public function down(): void
    {
        Schema::table('product_types', function (Blueprint $table) {
            $table->dropColumn(['code', 'is_active']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['code', 'contact_name', 'is_active']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
