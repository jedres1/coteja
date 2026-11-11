<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Change enum('borrador','completado') to string to support new state machine
        DB::statement("ALTER TABLE bank_reconciliations MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'abierta'");

        // Migrate existing status values
        DB::table('bank_reconciliations')->where('status', 'completado')->update(['status' => 'en_firme']);
        DB::table('bank_reconciliations')->where('status', 'borrador')->update(['status' => 'abierta']);

        Schema::table('bank_reconciliations', function (Blueprint $table) {
            // Precise cut date (may differ from last day of period_month)
            $table->date('cut_date')->nullable()->after('period_month');

            // Snapshot fields (populated when put en firme)
            $table->decimal('book_balance', 15, 2)->nullable()->after('statement_balance');
            $table->decimal('total_transit_out', 15, 2)->nullable()->after('book_balance');
            $table->decimal('total_transit_in', 15, 2)->nullable()->after('total_transit_out');
            $table->decimal('difference', 15, 2)->nullable()->after('total_transit_in');

            // Opening reconciliation flag
            $table->boolean('is_opening')->default(false)->after('notes');
            $table->string('reference_act', 100)->nullable()->after('is_opening');

            // Audit trail
            $table->foreignId('prepared_by')->nullable()->after('reference_act')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->after('prepared_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('completed_at');
            $table->foreignId('voided_by')->nullable()->after('reviewed_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('confirmed_at');
            $table->text('void_reason')->nullable()->after('voided_at');
        });

        // Backfill cut_date from period_year/period_month for existing records
        DB::statement("
            UPDATE bank_reconciliations
            SET cut_date = LAST_DAY(CONCAT(period_year, '-', LPAD(period_month, 2, '0'), '-01'))
            WHERE cut_date IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('prepared_by');
            $table->dropColumn([
                'cut_date', 'book_balance', 'total_transit_out', 'total_transit_in', 'difference',
                'is_opening', 'reference_act', 'confirmed_at', 'voided_at', 'void_reason',
            ]);
        });

        DB::statement("ALTER TABLE bank_reconciliations MODIFY COLUMN status ENUM('borrador','completado') NOT NULL DEFAULT 'borrador'");
        DB::table('bank_reconciliations')->where('status', 'en_firme')->update(['status' => 'completado']);
        DB::table('bank_reconciliations')->where('status', 'anulada')->update(['status' => 'borrador']);
    }
};
