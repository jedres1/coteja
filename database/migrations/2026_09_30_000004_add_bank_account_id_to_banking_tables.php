<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('id')
                ->constrained('bank_accounts')->nullOnDelete();
            $table->enum('type', ['deposito', 'pago', 'transferencia', 'cheque', 'otro'])
                ->default('pago')->after('bank_account_id');
        });

        Schema::table('billing_invoice_payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('billing_invoice_id')
                ->constrained('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn('type');
        });

        Schema::table('billing_invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });
    }
};
