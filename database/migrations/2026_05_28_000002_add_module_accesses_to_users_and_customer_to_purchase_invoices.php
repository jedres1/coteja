<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('module_accesses')->nullable()->after('is_active');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });

        DB::table('users')
            ->where('role', 'customer')
            ->whereNull('module_accesses')
            ->update(['module_accesses' => json_encode(['billing', 'purchases'])]);
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('module_accesses');
        });
    }
};
