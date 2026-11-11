<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AudioTracksApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_audio_migration_does_not_duplicate_existing_schema(): void
    {
        $migration = require database_path(
            'migrations/2026_10_17_000001_create_audio_tracks_and_link_exercises.php'
        );
        $migration->up();

        $this->assertTrue(Schema::hasTable('audio_tracks'));
        $this->assertTrue(Schema::hasColumn('exercises', 'optional_audio_track_id'));
        $this->assertTrue(collect(Schema::getForeignKeys('exercises'))->contains(
            fn (array $foreignKey) => in_array(
                'optional_audio_track_id',
                $foreignKey['columns'] ?? [],
                true
            ) && ($foreignKey['foreign_table'] ?? null) === 'audio_tracks'
        ));
    }

    public function test_admin_can_create_preview_and_publish_a_track_that_users_can_list_and_link_to_exercises(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $create = $this->post('/api/v1/admin/audio-tracks', [
            'title' => 'مقطع تجريبي',
            'slug' => 'test-audio-track',
            'description' => 'وصف للمقطع',
            'category' => 'calming',
            'duration_seconds' => 10,
            'audio' => UploadedFile::fake()
                ->createWithContent('test-track.m4a', '0123456789')
                ->mimeType('audio/mp4'),
        ]);

        $create->assertCreated();
        $trackId = $create->json('audio_track.id');

        $preview = $this->withHeader('Range', 'bytes=2-5')
            ->get('/api/v1/admin/audio-tracks/'.$trackId.'/preview');

        $preview
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 2-5/10');
        $this->assertSame('2345', $preview->streamedContent());

        $this->patchJson('/api/v1/admin/audio-tracks/'.$trackId.'/publish')
            ->assertOk()
            ->assertJsonPath('audio_track.is_published', true);

        $track = \App\Models\AudioTrack::query()->findOrFail($trackId);
        $exercise = Exercise::query()->create([
            'slug' => 'audio-linked-exercise',
            'title' => 'تمرين مرتبط',
            'description' => 'وصف التمرين',
            'type' => 'breathing',
            'duration_minutes' => 2,
            'content_steps' => [['title' => 'خطوة', 'body' => 'تفصيل']],
            'optional_audio_track_id' => $track->id,
            'is_published' => true,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/audio-tracks')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'test-audio-track');

        $this->getJson('/api/v1/exercises/'.$exercise->id)
            ->assertOk()
            ->assertJsonPath('exercise.optional_audio.slug', 'test-audio-track');
    }
}
