<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tablas que no tienen ningún campo de auditoría
        $plain = ['customers', 'suppliers', 'billing_invoices', 'purchase_invoices', 'employees'];
        foreach ($plain as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('created_by')->nullable()->after('id');
                $t->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $t->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        // journal_entries ya tiene created_by — solo agrega updated_by
        Schema::table('journal_entries', function (Blueprint $t) {
            $t->unsignedBigInteger('updated_by')->nullable()->after('approved_by');
            $t->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $plain = ['customers', 'suppliers', 'billing_invoices', 'purchase_invoices', 'employees'];
        foreach ($plain as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['created_by']);
                $t->dropForeign(['updated_by']);
                $t->dropColumn(['created_by', 'updated_by']);
            });
        }

        Schema::table('journal_entries', function (Blueprint $t) {
            $t->dropForeign(['updated_by']);
            $t->dropColumn('updated_by');
        });
    }
};
