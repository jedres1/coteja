<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_dte_correlatives', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 2);
            $table->unsignedSmallInteger('year');
            $table->string('establishment', 4)->default('');
            $table->string('point_of_sale', 4)->default('');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['document_type', 'year', 'establishment', 'point_of_sale'], 'billing_dte_correlatives_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_dte_correlatives');
        Schema::dropIfExists('billing_settings');
    }
};
