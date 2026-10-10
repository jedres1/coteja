<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address_district', 50)->nullable()->after('address_municipality');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('address_district', 50)->nullable()->after('address_municipality');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('address_district');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('address_district');
        });
    }
};
