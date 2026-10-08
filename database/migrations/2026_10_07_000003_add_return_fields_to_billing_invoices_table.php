<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->string('return_stamp')->nullable()->after('void_json');
            $table->timestamp('returned_at')->nullable()->after('return_stamp');
            $table->json('return_json')->nullable()->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropColumn(['return_stamp', 'returned_at', 'return_json']);
        });
    }
};
