<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->string('mode', 32)->default('retrieval');
            $table->string('request_fingerprint', 64)->nullable();
        });
        Schema::create('recommendation_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_type', 16);
            $table->unsignedBigInteger('item_id');
            $table->smallInteger('rating');
            $table->timestamps();
            $table->unique(['user_id', 'item_type', 'item_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('recommendation_feedback');
        Schema::table('support_messages', fn (Blueprint $table) => $table->dropColumn(['mode', 'request_fingerprint']));
    }
};
