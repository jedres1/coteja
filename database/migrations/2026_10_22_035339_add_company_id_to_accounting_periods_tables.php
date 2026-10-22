<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_year_periods', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
            $table->dropUnique('accounting_year_periods_year_unique');
            $table->unique(['company_id', 'year']);
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
            $table->dropUnique('accounting_periods_year_month_unique');
            $table->unique(['company_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('accounting_year_periods', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'year']);
            $table->dropConstrainedForeignId('company_id');
            $table->unique('year');
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'year', 'month']);
            $table->dropConstrainedForeignId('company_id');
            $table->unique(['year', 'month']);
        });
    }
};
