<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->date('last_reconciliation_date')->nullable()->after('opening_balance');
            $table->foreignId('accounting_account_id')->nullable()->after('last_reconciliation_date')
                ->constrained('accounting_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_account_id');
            $table->dropColumn('last_reconciliation_date');
        });
    }
};
