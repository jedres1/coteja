<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('accounting_package_id')
                  ->nullable()
                  ->after('notes')
                  ->constrained('accounting_packages')
                  ->nullOnDelete();

            $table->string('source_type', 50)->nullable()->after('accounting_package_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('source_document', 150)->nullable()->after('source_id');

            $table->index(['source_type', 'source_id'], 'je_source_idx');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('je_source_idx');
            $table->dropConstrainedForeignId('accounting_package_id');
            $table->dropColumn(['source_type', 'source_id', 'source_document']);
        });
    }
};
