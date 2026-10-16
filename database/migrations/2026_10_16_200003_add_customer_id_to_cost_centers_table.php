<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            $table->dropUnique('cost_centers_code_unique');
            $table->unique(['customer_id', 'code'], 'cost_centers_customer_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropUnique('cost_centers_customer_code_unique');
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
            $table->unique('code');
        });
    }
};
