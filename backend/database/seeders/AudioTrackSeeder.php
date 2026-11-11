<?php

namespace Database\Seeders;

use App\Models\AudioTrack;
use App\Models\Exercise;
use App\Services\AudioStorageService;
use App\Services\ImageStorageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class AudioTrackSeeder extends Seeder
{
    public function run(): void
    {
        $storage = app(AudioStorageService::class);
        $images = app(ImageStorageService::class);

        foreach ($this->tracks() as $data) {
            $filename = $data['filename'];
            $imageFilename = $data['image_filename'];
            unset($data['filename'], $data['image_filename']);

            $source = database_path('seeders/fixtures/audio-tracks/'.$filename);
            $cover = database_path('seeders/fixtures/images/audio-tracks/'.$imageFilename);
            $track = AudioTrack::withTrashed()->firstOrNew(['slug' => $data['slug']]);

            if ($track->exists && $track->trashed()) {
                continue;
            }

            if (! $track->exists) {
                $track->fill($data);
                $track->audio_path = $storage->storeFixture($source, $filename);
                $track->file_size_bytes = filesize($source);
                $track->image_url = $images->storeFixture(
                    $cover,
                    ImageStorageService::FOLDER_AUDIO_TRACK_COVERS,
                    $imageFilename,
                );
                $track->save();

                continue;
            }

            $fixturePath = AudioStorageService::FOLDER_TRACKS.'/'.$filename;
            if (! $track->audio_path || ($track->audio_path === $fixturePath && ! Storage::disk(AudioStorageService::DISK)->exists($fixturePath))) {
                $track->audio_path = $storage->storeFixture($source, $filename);
                $track->file_size_bytes = filesize($source);
                $track->save();
            }

            if (! $track->image_url) {
                $track->image_url = $images->storeFixture(
                    $cover,
                    ImageStorageService::FOLDER_AUDIO_TRACK_COVERS,
                    $imageFilename,
                );
                $track->save();
            }
        }

        $this->linkExerciseCompanions();
    }

    private function tracks(): array
    {
        return [
            [
                'slug' => 'morning-light',
                'title' => 'ضوء الصباح',
                'description' => 'مساحة صوتية مشرقة وهادئة لبداية اليوم بحضور وطاقة لطيفة.',
                'category' => 'morning',
                'duration_seconds' => 471,
                'filename' => 'morning_light_final.m4a',
                'image_filename' => 'morning-light.webp',
                'credit' => 'فريق نفس',
                'license_type' => 'owned',
                'license_source_url' => null,
                'mime_type' => 'audio/mp4',
                'is_published' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'gentle-focus',
                'title' => 'تركيز لطيف',
                'description' => 'مساحة صوتية متوازنة تساعد على الدراسة والعمل دون إيقاع مشتت.',
                'category' => 'focus',
                'duration_seconds' => 1200,
                'filename' => 'gentle_focus_final.m4a',
                'image_filename' => 'gentle-focus.webp',
                'credit' => 'فريق نفس',
                'license_type' => 'owned',
                'license_source_url' => null,
                'mime_type' => 'audio/mp4',
                'is_published' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'window-and-rain',
                'title' => 'نافذة ومطر',
                'description' => 'مطر وموسيقا دافئة لمساحة هادئة تساعد على الاسترخاء وفك التوتر.',
                'category' => 'calming',
                'duration_seconds' => 480,
                'filename' => 'window_and_rain_final.m4a',
                'image_filename' => 'window-and-rain.webp',
                'credit' => 'فريق نفس',
                'license_type' => 'owned',
                'license_source_url' => null,
                'mime_type' => 'audio/mp4',
                'is_published' => true,
                'sort_order' => 3,
            ],
        ];
    }

    private function linkExerciseCompanions(): void
    {
        $links = [
            'morning-daylight-pause' => 'morning-light',
            'thought-dump' => 'gentle-focus',
            'weekly-review' => 'gentle-focus',
            'mindful-meditation' => 'window-and-rain',
            'calm-sleep-routine' => 'window-and-rain',
            'shoulder-relaxation' => 'window-and-rain',
        ];

        $trackIds = AudioTrack::query()
            ->whereIn('slug', array_values($links))
            ->pluck('id', 'slug');

        foreach ($links as $exerciseSlug => $trackSlug) {
            if (! $trackIds->has($trackSlug)) {
                continue;
            }

            Exercise::query()
                ->where('slug', $exerciseSlug)
                ->whereNull('optional_audio_track_id')
                ->update(['optional_audio_track_id' => $trackIds[$trackSlug]]);
        }
    }
}
