<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->enum('payment_status', ['pendiente', 'parcial', 'pagado'])->default('pendiente')->after('accepted');
            $table->decimal('amount_paid', 12, 2)->default(0)->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('amount_paid');
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'amount_paid', 'paid_at']);
        });
    }
};
