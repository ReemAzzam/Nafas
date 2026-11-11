<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wellness_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('snapshot');
            $table->timestamps();
        });
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('request_key', 64)->nullable();
            $table->unique(['user_id', 'request_key']);
            $table->text('content'); // encrypted cast; allow ciphertext expansion
            $table->json('sources')->nullable();
            $table->boolean('urgent')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('wellness_profiles');
    }
};
