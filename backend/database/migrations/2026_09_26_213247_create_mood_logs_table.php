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
        Schema::create('mood_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('mood_score');
            $table->string('suggestion_type', 20)->nullable();
            $table->json('factors')->nullable();
            $table->text('note')->nullable(); // سيتم تشفيره في الـ Model
            $table->foreignId('suggested_exercise_id')->nullable()->constrained('exercises')->onDelete('set null');
            $table->foreignId('suggested_article_id')->nullable()
                ->constrained('articles')->nullOnDelete();
            $table->json('dismissed_suggestions')->nullable();
            $table->timestamp('suggestion_completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mood_logs');
    }
};
