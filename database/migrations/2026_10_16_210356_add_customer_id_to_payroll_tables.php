<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // employees: código único global → único por inquilino
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_code_unique');
            $table->unique(['customer_id', 'code'], 'employees_customer_code_unique');
        });

        // payroll_periods: identificar a qué inquilino pertenece cada período
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        // payroll_concepts: system_key único global → único por inquilino
        Schema::table('payroll_concepts', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });
        Schema::table('payroll_concepts', function (Blueprint $table) {
            $table->dropUnique('payroll_concepts_system_key_unique');
            $table->unique(['customer_id', 'system_key'], 'payroll_concepts_customer_system_key_unique');
        });

        // payroll_settings: una configuración por inquilino
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('payroll_concepts', function (Blueprint $table) {
            $table->dropUnique('payroll_concepts_customer_system_key_unique');
        });
        Schema::table('payroll_concepts', function (Blueprint $table) {
            $table->unique('system_key', 'payroll_concepts_system_key_unique');
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_customer_code_unique');
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->unique('code', 'employees_code_unique');
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
