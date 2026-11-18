<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('status');
            $table->timestamp('correction_saved_at')->nullable()->after('rejected_at');
        });

        DB::table('billing_invoices')
            ->where('status', 'RECHAZADO')
            ->whereNull('rejected_at')
            ->update(['rejected_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropColumn(['rejected_at', 'correction_saved_at']);
        });
    }
};
