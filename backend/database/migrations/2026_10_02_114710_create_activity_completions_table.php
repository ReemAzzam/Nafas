<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('activity_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type');
            $table->unsignedBigInteger('activity_id');
            $table->string('source', 20);
            $table->foreignId('plan_activity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mood_log_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['user_id', 'plan_activity_id'],
                'activity_completions_user_plan_activity_unique'
            );
            $table->unique(
                ['user_id', 'mood_log_id'],
                'activity_completions_user_mood_log_unique'
            );
            $table->index(['user_id', 'activity_type', 'activity_id']);
            $table->index(['user_id', 'created_at'], 'activity_completions_user_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_completions');

    }
};
