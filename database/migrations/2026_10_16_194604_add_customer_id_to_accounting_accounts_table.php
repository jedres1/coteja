<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            // Reemplazar unique(code) global por unique(customer_id, code) por inquilino.
            // MySQL permite múltiples NULLs en índices únicos, por lo que los registros
            // plantilla (customer_id=NULL) coexisten con las copias por cliente.
            $table->dropUnique('accounting_accounts_code_unique');
            $table->unique(['customer_id', 'code'], 'accounting_accounts_customer_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $table) {
            $table->dropUnique('accounting_accounts_customer_code_unique');
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
            $table->unique('code');
        });
    }
};
