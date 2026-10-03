<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['borrador', 'aplicado'])->default('borrador');
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->dateTime('applied_at')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->foreign('applied_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->decimal('salary', 10, 2);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('overtime_amount', 10, 2)->default(0);
            $table->decimal('bonuses', 10, 2)->default(0);
            $table->decimal('gross_salary', 10, 2);
            $table->decimal('isss_employee', 10, 2);
            $table->decimal('afp_employee', 10, 2);
            $table->decimal('isr', 10, 2);
            $table->decimal('total_deductions', 10, 2);
            $table->decimal('net_salary', 10, 2);
            $table->decimal('isss_employer', 10, 2);
            $table->decimal('afp_employer', 10, 2);
            $table->decimal('total_employer_cost', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accounting_package_id')->nullable();
            $table->foreign('accounting_package_id')->references('id')->on('accounting_packages')->nullOnDelete();
            $table->foreignId('account_salaries_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('account_isss_employer_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('account_afp_employer_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('account_isss_employee_payable_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('account_isss_employer_payable_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('account_afp_employee_payable_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('account_afp_employer_payable_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('account_isr_payable_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->foreignId('account_salaries_payable_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->decimal('isss_salary_cap', 10, 2)->default(1000);
            $table->decimal('isss_employee_rate', 5, 4)->default(0.0300);
            $table->decimal('isss_employer_rate', 5, 4)->default(0.0750);
            $table->decimal('afp_employee_rate', 5, 4)->default(0.0725);
            $table->decimal('afp_employer_rate', 5, 4)->default(0.0875);
            $table->timestamps();
        });

        // Insert NOM accounting package
        DB::table('accounting_packages')->insert([
            'code'             => 'NOM',
            'name'             => 'Nómina',
            'description'      => 'Generado al aplicar períodos de nómina.',
            'type'             => 'automatico',
            'is_active'        => true,
            'last_correlative' => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employees');
        DB::table('accounting_packages')->where('code', 'NOM')->delete();
    }
};
