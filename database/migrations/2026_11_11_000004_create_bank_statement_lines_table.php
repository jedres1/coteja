<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->date('line_date');
            $table->string('reference', 50)->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 15, 2); // positive = credit (deposit), negative = debit
            $table->foreignId('transaction_id')->nullable()
                ->constrained('bank_transactions')->nullOnDelete();
            $table->string('match_status', 20)->default('unmatched'); // unmatched | suggested | confirmed
            $table->timestamps();

            $table->index(['reconciliation_id', 'match_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
