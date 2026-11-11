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
        Schema::create('plan_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_day_id')->constrained('plan_days')->onDelete('cascade');

            // Polymorphic Relation للتمارين والمقالات
            $table->string('activity_type'); // 'App\Models\Exercise' or 'App\Models\Article'
            $table->unsignedBigInteger('activity_id');

            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            $table->index(['activity_type', 'activity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_activities');
    }
};
