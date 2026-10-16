<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            $table->dropUnique('journal_entries_entry_number_unique');
            $table->unique(['customer_id', 'entry_number'], 'journal_entries_customer_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropUnique('journal_entries_customer_number_unique');
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
            $table->unique('entry_number');
        });
    }
};
