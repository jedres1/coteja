<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_clients', function (Blueprint $table) {
            $table->string('address_district', 6)->nullable()->after('address_municipality');
        });
    }

    public function down(): void
    {
        Schema::table('billing_clients', function (Blueprint $table) {
            $table->dropColumn('address_district');
        });
    }
};
