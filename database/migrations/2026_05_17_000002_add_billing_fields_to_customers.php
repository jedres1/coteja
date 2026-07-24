<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('document_type', 2)->default('13')->after('phone');
            $table->string('document_number', 50)->nullable()->after('document_type');
            $table->string('nrc', 50)->nullable()->after('document_number');
            $table->string('trade_name')->nullable()->after('nrc');
            $table->string('business_activity', 10)->nullable()->after('trade_name');
            $table->string('activity_description')->nullable()->after('business_activity');
            $table->string('address_department', 2)->nullable()->after('activity_description');
            $table->string('address_municipality', 2)->nullable()->after('address_department');
            $table->text('address')->nullable()->after('address_municipality');
            $table->string('preferred_dte_type', 2)->default('01')->after('address');
            $table->string('billing_email')->nullable()->after('preferred_dte_type');
            $table->string('billing_phone', 50)->nullable()->after('billing_email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'document_type',
                'document_number',
                'nrc',
                'trade_name',
                'business_activity',
                'activity_description',
                'address_department',
                'address_municipality',
                'address',
                'preferred_dte_type',
                'billing_email',
                'billing_phone',
            ]);
        });
    }
};
