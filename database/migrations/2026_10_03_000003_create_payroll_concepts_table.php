<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_concepts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['beneficio', 'deduccion']);
            $table->enum('calculation_method', ['fijo', 'porcentaje', 'formula', 'editable']);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('rate', 8, 4)->default(0);
            $table->string('formula', 255)->nullable();
            $table->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->boolean('applies_to_all')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_payroll_concept', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_concept_id')->constrained()->cascadeOnDelete();
            $table->primary(['employee_id', 'payroll_concept_id']);
        });

        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->json('concept_details')->nullable()->after('total_employer_cost');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropColumn('concept_details');
        });
        Schema::dropIfExists('employee_payroll_concept');
        Schema::dropIfExists('payroll_concepts');
    }
};
