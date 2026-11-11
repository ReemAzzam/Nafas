<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Name يكون Nullable لدعم الدخول المجهول (Anonymous)
            $table->string('name')->nullable();
            $table->string('display_name', 80)->nullable();
            $table->string('email');
            $table->string('password');
            $table->string('country_code', 5)->nullable(); // رمز الدولة (مثل: +963)
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('profile_image_url')->nullable();

            $table->enum('status', ['student', 'worker', 'other'])->nullable();
            $table->enum('onboarding_status', ['pending', 'goals_set', 'assessed', 'plan_ready', 'skipped'])->default('pending');

            $table->enum('role', ['user', 'admin'])->default('user');
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_blocked')->default(false); // لحظر المستخدم من لوحة الأدمن
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->string('active_email')
                ->storedAs('CASE WHEN deleted_at IS NULL THEN LOWER(email) ELSE NULL END')
                ->unique();

            // Indexing لتحسين أداء الاستعلامات
            $table->index('email');
            $table->index('role');
            $table->index('is_blocked');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
