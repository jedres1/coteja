<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->smallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->string('statement_file_path')->nullable();
            $table->decimal('statement_balance', 15, 2)->nullable();
            $table->enum('status', ['borrador', 'completado'])->default('borrador');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['bank_account_id', 'period_year', 'period_month'], 'bank_recon_account_period_unique');
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('reconciliation_id')->nullable()->after('bank_account_id')
                ->constrained('bank_reconciliations')->nullOnDelete();
            $table->boolean('is_reconciled')->default(false)->after('reconciliation_id');
        });

        Schema::table('billing_invoice_payments', function (Blueprint $table) {
            $table->foreignId('reconciliation_id')->nullable()->after('bank_account_id')
                ->constrained('bank_reconciliations')->nullOnDelete();
            $table->boolean('is_reconciled')->default(false)->after('reconciliation_id');
        });
    }

    public function down(): void {
        Schema::table('billing_invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciliation_id');
            $table->dropColumn('is_reconciled');
        });
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciliation_id');
            $table->dropColumn('is_reconciled');
        });
        Schema::dropIfExists('bank_reconciliations');
    }
};
