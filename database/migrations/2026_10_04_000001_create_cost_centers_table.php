<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('accounting_packages', function (Blueprint $table) {
            $table->foreignId('cost_center_id')
                ->nullable()
                ->after('secondary_credit_account_id')
                ->constrained('cost_centers')
                ->nullOnDelete();
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->foreignId('cost_center_id')
                ->nullable()
                ->after('account_id')
                ->constrained('cost_centers')
                ->nullOnDelete();
        });

        DB::table('cost_centers')->insertOrIgnore([
            [
                'code' => 'GEN',
                'name' => 'General',
                'description' => 'Centro de costo general para operaciones sin asignación específica.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $generalId = DB::table('cost_centers')->where('code', 'GEN')->value('id');
        if ($generalId) {
            DB::table('accounting_packages')
                ->whereNull('cost_center_id')
                ->update(['cost_center_id' => $generalId, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
        });

        Schema::table('accounting_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
        });

        Schema::dropIfExists('cost_centers');
    }
};
