<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\ContentImageSeeder;
use Database\Seeders\NafasDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExerciseCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_wellness_exercises_are_seeded_with_images_and_lions_breath_video(): void
    {
        Storage::fake('public');

        $this->seed(NafasDemoSeeder::class);
        $this->seed(ContentImageSeeder::class);

        foreach ([
            'mindful-meditation' => 'mindful-meditation.png',
            'mindful-walking' => 'mindful-walking.png',
            'feet-grounding' => 'feet-grounding.png',
            'morning-daylight-pause' => 'morning-daylight.png',
            'kind-mirror-talk' => 'mirror-self-talk.png',
        ] as $slug => $image) {
            $this->assertDatabaseHas('exercises', [
                'slug' => $slug,
                'image_url' => 'exercises/'.$image,
                'is_published' => true,
            ]);
            Storage::disk('public')->assertExists('exercises/'.$image);
        }

        $this->assertDatabaseHas('exercises', [
            'slug' => 'lions-breath',
            'media_type' => 'video',
            'media_url' => 'https://www.youtube.com/shorts/CdnAL_gtIB0',
            'media_credit' => 'ملاك فاعور',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $lion = Exercise::query()->where('slug', 'lions-breath')->firstOrFail();
        $this->getJson('/api/v1/exercises/'.$lion->id)
            ->assertOk()
            ->assertJsonPath('exercise.media.type', 'video')
            ->assertJsonPath('exercise.media.credit', 'ملاك فاعور');
    }
}
