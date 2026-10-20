<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('company_id');
            $table->unsignedTinyInteger('level')->default(1)->after('parent_id');
            $table->foreign('parent_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn(['parent_id', 'level']);
        });
    }
};
