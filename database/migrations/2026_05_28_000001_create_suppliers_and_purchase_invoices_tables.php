<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('document_type');
            $table->string('document_number')->index();
            $table->string('nrc')->nullable()->index();
            $table->string('trade_name')->nullable();
            $table->string('business_activity', 10)->nullable();
            $table->string('activity_description')->nullable();
            $table->string('address_department', 2);
            $table->string('address_municipality', 4);
            $table->string('address', 500);
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('document_type')->default('03');
            $table->string('invoice_number')->index();
            $table->date('purchase_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('iva', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('status')->default('registered');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
        Schema::dropIfExists('suppliers');
    }
};
