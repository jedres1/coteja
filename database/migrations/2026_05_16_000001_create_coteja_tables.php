<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('customer')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('trade_name')->nullable();
            $table->string('nit_dui')->index();
            $table->string('nrc')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('environment')->default('production');
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('annual_price', 10, 2)->default(0);
            $table->decimal('implementation_fee', 10, 2)->default(0);
            $table->decimal('additional_user_price', 10, 2)->default(0);
            $table->unsignedInteger('included_users')->default(1);
            $table->unsignedInteger('max_devices')->default(1);
            $table->boolean('support_included')->default(false);
            $table->boolean('cloud_backup')->default(false);
            $table->boolean('unlimited_documents')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('license_key')->unique();
            $table->string('status')->default('active');
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('max_users')->default(1);
            $table->unsignedInteger('max_devices')->default(1);
            $table->unsignedInteger('grace_days')->default(7);
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('license_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->string('device_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['license_id', 'device_id']);
        });

        Schema::create('license_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('role')->default('operator');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('iva', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('period')->default('monthly');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->string('status')->default('paid');
            $table->date('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum')->nullable();
            $table->string('encryption')->default('aes-256-gcm');
            $table->string('compression')->default('gzip');
            $table->string('device_id')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('version');
            $table->string('filename');
            $table->string('download_url');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('license_users');
        Schema::dropIfExists('license_devices');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('companies');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['role', 'is_active']);
        });
        Schema::dropIfExists('customers');
    }
};
