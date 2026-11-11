<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 300)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_note', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['article_comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_comment_reports');
    }
};
