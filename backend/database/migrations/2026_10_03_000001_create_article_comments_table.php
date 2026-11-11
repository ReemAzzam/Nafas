<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('visible')->index();
            $table->string('moderation_status', 20)->default('not_checked')->index();
            $table->decimal('moderation_score', 5, 4)->nullable();
            $table->json('moderation_labels')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_comments');
    }
};
