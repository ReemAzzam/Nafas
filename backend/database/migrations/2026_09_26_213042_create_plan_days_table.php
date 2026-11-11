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
        Schema::create('plan_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wellness_plan_id')->constrained('wellness_plans')->onDelete('cascade');
            $table->integer('day_number'); // 1 إلى 7
            $table->date('date'); // التاريخ الفعلي لليوم
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            $table->unique(['wellness_plan_id', 'day_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_days');
    }
};
