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
        Schema::create('mood_log_factor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mood_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('factor_id')->constrained()->cascadeOnDelete();
            $table->unique(['mood_log_id', 'factor_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mood_log_factor');
    }
};
