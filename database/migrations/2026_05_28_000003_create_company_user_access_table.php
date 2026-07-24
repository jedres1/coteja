<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'company_id']);
        });

        $users = DB::table('users')->where('role', 'customer')->whereNotNull('customer_id')->get();

        foreach ($users as $user) {
            $companyIds = DB::table('companies')->where('customer_id', $user->customer_id)->pluck('id');

            foreach ($companyIds as $companyId) {
                DB::table('company_user')->insertOrIgnore([
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
