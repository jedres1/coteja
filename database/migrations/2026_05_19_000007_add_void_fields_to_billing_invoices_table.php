<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->string('void_stamp')->nullable()->after('reception_stamp');
            $table->timestamp('voided_at')->nullable()->after('void_stamp');
            $table->string('void_reason')->nullable()->after('voided_at');
            $table->json('void_json')->nullable()->after('void_reason');
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropColumn(['void_stamp', 'voided_at', 'void_reason', 'void_json']);
        });
    }
};
