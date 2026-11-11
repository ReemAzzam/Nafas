<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audio_tracks')) {
            Schema::create('audio_tracks', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('category', 50);
                $table->unsignedInteger('duration_seconds');
                $table->string('audio_path')->unique();
                $table->string('image_url')->nullable();
                $table->string('credit')->default('فريق نفس');
                $table->string('license_type', 50)->nullable();
                $table->text('license_source_url')->nullable();
                $table->string('mime_type', 100)->default('audio/mp4');
                $table->unsignedBigInteger('file_size_bytes')->nullable();
                $table->boolean('is_published')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->index(
                    ['is_published', 'category', 'sort_order'],
                    'audio_tracks_catalog_index'
                );
            });
        }

        if (! Schema::hasTable('exercises')) {
            throw new RuntimeException('Cannot add audio track relation: exercises table is missing.');
        }

        if (! Schema::hasColumn('exercises', 'optional_audio_track_id')) {
            Schema::table('exercises', function (Blueprint $table) {
                $table->foreignId('optional_audio_track_id')
                    ->nullable()
                    ->constrained('audio_tracks')
                    ->nullOnDelete();
            });

            return;
        }

        $hasAudioTrackForeignKey = collect(Schema::getForeignKeys('exercises'))
            ->contains(fn (array $foreignKey) => in_array(
                'optional_audio_track_id',
                $foreignKey['columns'] ?? [],
                true
            ) && ($foreignKey['foreign_table'] ?? null) === 'audio_tracks');

        if (! $hasAudioTrackForeignKey) {
            Schema::table('exercises', function (Blueprint $table) {
                $table->foreign('optional_audio_track_id')
                    ->references('id')
                    ->on('audio_tracks')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {

     // Intentionally empty.
    }
};
