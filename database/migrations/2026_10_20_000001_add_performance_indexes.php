<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compound covering index for aggregate reports (balanza, libro mayor)
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->index(['account_id', 'debit', 'credit'], 'jel_account_debit_credit_idx');
            $table->index(['journal_entry_id', 'account_id'], 'jel_entry_account_idx');
        });

        // Compound indexes for filtered invoice listings
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->index(['company_id', 'status'], 'bi_company_status_idx');
            $table->index(['customer_id', 'issued_at'], 'bi_customer_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropIndex('jel_account_debit_credit_idx');
            $table->dropIndex('jel_entry_account_idx');
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropIndex('bi_company_status_idx');
            $table->dropIndex('bi_customer_date_idx');
        });
    }
};
