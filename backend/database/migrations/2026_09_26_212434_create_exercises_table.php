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
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description');
            $table->enum('type', ['breathing', 'grounding', 'journaling', 'relaxation']);
            $table->integer('duration_minutes')->default(5);
            $table->json('content_steps')->nullable();
            $table->enum('media_type', ['video', 'audio'])->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_credit')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedBigInteger('optional_audio_track_id')->nullable()->index();
            $table->boolean('is_published')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
