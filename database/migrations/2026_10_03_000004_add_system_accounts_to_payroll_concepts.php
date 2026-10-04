<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_concepts', function (Blueprint $table) {
            $table->string('system_key', 40)->nullable()->unique();
            $table->foreignId('payable_account_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_concepts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payable_account_id');
            $table->dropUnique(['system_key']);
            $table->dropColumn('system_key');
        });
    }
};