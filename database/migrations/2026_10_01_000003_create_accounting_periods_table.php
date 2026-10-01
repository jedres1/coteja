<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->string('name', 100);          // "Enero 2026", "Diciembre 2026 (Cierre)"
            $table->enum('status', ['abierto', 'cerrado'])->default('cerrado');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index(['year', 'status']);
        });

        // Abrir el período del mes actual automáticamente
        $months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $now   = now();
        $year  = $now->year;
        $month = $now->month;
        $name  = $months[$month] . " {$year}" . ($month === 12 ? ' (Cierre)' : '');

        DB::table('accounting_periods')->insertOrIgnore([
            'year'       => $year,
            'month'      => $month,
            'name'       => $name,
            'status'     => 'abierto',
            'opened_at'  => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
