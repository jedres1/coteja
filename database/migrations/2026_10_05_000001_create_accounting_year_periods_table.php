<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_year_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('name', 100);
            $table->enum('status', ['abierto', 'cerrado'])->default('cerrado');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        $now = now();
        $years = DB::table('accounting_periods')
            ->distinct()
            ->orderBy('year')
            ->pluck('year')
            ->push($now->year)
            ->unique()
            ->values();

        foreach ($years as $year) {
            $hasOpenMonth = DB::table('accounting_periods')
                ->where('year', $year)
                ->where('status', 'abierto')
                ->exists();

            DB::table('accounting_year_periods')->insertOrIgnore([
                'year' => $year,
                'name' => 'Ejercicio contable '.$year,
                'status' => $hasOpenMonth ? 'abierto' : 'cerrado',
                'opened_at' => $hasOpenMonth ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_year_periods');
    }
};
