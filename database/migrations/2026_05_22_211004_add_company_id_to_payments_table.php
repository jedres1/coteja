<?php

use App\Models\License;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'company_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->unsignedBigInteger('company_id')->nullable()->after('license_id');
                $table->index('company_id');

                return;
            }

            $table->foreignId('company_id')
                ->nullable()
                ->after('license_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        License::query()
            ->select(['id', 'company_id'])
            ->with('payments:id,license_id')
            ->chunkById(100, function ($licenses) {
                foreach ($licenses as $license) {
                    $license->payments()->update(['company_id' => $license->company_id]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'company_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->dropIndex(['company_id']);
                $table->dropColumn('company_id');

                return;
            }

            $table->dropConstrainedForeignId('company_id');
        });
    }
};
