<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address_department', 2)->nullable()->after('business_activity');
            $table->string('address_municipality', 4)->nullable()->after('address_department');
            $table->string('address_district', 6)->nullable()->after('address_municipality');
            $table->string('address')->nullable()->after('address_district');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['address_department', 'address_municipality', 'address_district', 'address']);
        });
    }
};
