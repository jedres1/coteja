<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('dui', 10)->nullable();
            $table->string('isss_number')->nullable();
            $table->string('nup')->nullable();
            $table->enum('afp', ['crecer', 'confia'])->default('crecer');
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->date('hire_date');
            $table->decimal('salary', 10, 2);
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
