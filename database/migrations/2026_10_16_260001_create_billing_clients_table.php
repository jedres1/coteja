<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('document_type', 10)->nullable();
            $table->string('document_number', 50)->nullable();
            $table->string('nrc', 30)->nullable();
            $table->string('business_activity', 10)->nullable();
            $table->string('activity_description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone', 30)->nullable();
            $table->string('address_department')->nullable();
            $table->string('address_municipality')->nullable();
            $table->string('address')->nullable();
            $table->string('preferred_dte_type', 5)->nullable()->default('01');
            $table->boolean('is_clients_varios')->default(false);
            $table->timestamps();
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->foreignId('billing_client_id')->nullable()->after('customer_id')
                ->constrained('billing_clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\BillingClient::class);
            $table->dropColumn('billing_client_id');
        });

        Schema::dropIfExists('billing_clients');
    }
};
