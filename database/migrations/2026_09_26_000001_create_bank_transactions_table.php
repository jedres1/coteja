<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::dropIfExists('bank_transaction_purchase_invoice');
        Schema::dropIfExists('bank_transactions');

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->string('bank_account', 200)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('reference', 200)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_transaction_purchase_invoice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_applied', 10, 2)->nullable();
            $table->unique(['bank_transaction_id', 'purchase_invoice_id'], 'bt_pi_unique');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('bank_transaction_purchase_invoice');
        Schema::dropIfExists('bank_transactions');
    }
};
