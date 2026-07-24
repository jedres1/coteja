<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number_control')->unique();
            $table->string('generation_code', 36)->unique();
            $table->string('document_type', 2);
            $table->date('issued_at');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status', 30)->default('PENDIENTE');
            $table->boolean('has_error')->default(false);
            $table->boolean('accepted')->default(false);
            $table->boolean('email_sent')->default(false);
            $table->string('reception_stamp')->nullable();
            $table->longText('observations')->nullable();
            $table->json('json_dte')->nullable();
            $table->json('signed_dte')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
    }
};
