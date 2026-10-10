<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
