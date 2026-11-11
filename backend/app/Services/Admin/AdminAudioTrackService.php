<?php

namespace App\Services\Admin;

use App\Models\AudioTrack;
use App\Services\AudioStorageService;
use App\Services\ImageStorageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminAudioTrackService
{
    public function __construct(
        private AudioStorageService $audio,
        private ImageStorageService $images,
    ) {}

    public function save(
        ?AudioTrack $track,
        array $data,
        ?UploadedFile $audioFile,
        ?UploadedFile $image,
    ): AudioTrack {
        $track ??= new AudioTrack;
        $oldAudioPath = $track->audio_path;
        $oldImagePath = $track->image_url;
        $newAudioPath = null;
        $newImagePath = null;

        try {
            if ($audioFile) {
                $newAudioPath = $this->audio->storeUpload($audioFile);
                $data['audio_path'] = $newAudioPath;
                $data['mime_type'] = $audioFile->getMimeType() ?: 'audio/mp4';
                $data['file_size_bytes'] = $audioFile->getSize();
            }

            if ($image) {
                $newImagePath = $this->images->storeUpload(
                    $image,
                    ImageStorageService::FOLDER_AUDIO_TRACK_COVERS
                );
                $data['image_url'] = $newImagePath;
            } elseif ($data['remove_image'] ?? false) {
                $data['image_url'] = null;
            }

            $data['license_type'] = 'owned';
            $data['credit'] ??= 'فريق نفس';

            if (! $track->exists) {
                $data['is_published'] = false;
                $data['sort_order'] ??= 0;
            }

            DB::transaction(function () use ($track, $data): void {
                $track->fill(Arr::except($data, ['audio', 'image', 'remove_image']))->save();
            });
        } catch (Throwable $exception) {
            $this->audio->delete($newAudioPath);
            $this->images->delete($newImagePath);

            throw $exception;
        }

        if ($newAudioPath && $newAudioPath !== $oldAudioPath) {
            $this->audio->delete($oldAudioPath);
        }

        if (($newImagePath || ($data['remove_image'] ?? false)) && $oldImagePath !== $newImagePath) {
            $this->images->delete($oldImagePath);
        }

        return $track->refresh();
    }

    public function publish(AudioTrack $track): AudioTrack
    {
        if (! $track->audio_path
            || ! Storage::disk(AudioStorageService::DISK)->exists($track->audio_path)) {
            throw ValidationException::withMessages([
                'audio' => ['لا يمكن نشر المقطع لأن ملفه الصوتي غير موجود.'],
            ]);
        }

        $track->update(['is_published' => true]);

        return $track->refresh();
    }

    public function unpublish(AudioTrack $track): AudioTrack
    {
        return DB::transaction(function () use ($track) {
            $lockedTrack = AudioTrack::query()
                ->whereKey($track->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureNotUsedByActivePlan(
                $lockedTrack,
                'لا يمكن إلغاء نشر المقطع لأنه مستخدم ضمن خطة نشطة.'
            );
            $lockedTrack->update(['is_published' => false]);

            return $lockedTrack->refresh();
        });
    }

    public function delete(AudioTrack $track): void
    {
        DB::transaction(function () use ($track): void {
            $lockedTrack = AudioTrack::query()
                ->whereKey($track->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureNotUsedByActivePlan(
                $lockedTrack,
                'لا يمكن حذف المقطع لأنه مستخدم ضمن خطة نشطة.'
            );
            $lockedTrack->delete();
        });
    }

    public function restore(AudioTrack $track): AudioTrack
    {
        if (! $track->trashed()) {
            throw ValidationException::withMessages([
                'audio_track' => ['المقطع الصوتي غير محذوف.'],
            ]);
        }

        $track->restore();
        $track->update(['is_published' => false]);

        return $track->refresh();
    }

    private function ensureNotUsedByActivePlan(AudioTrack $track, string $message): void
    {
        $isUsed = $track->planActivities()
            ->whereHas('planDay.wellnessPlan', fn ($query) => $query
                ->where('is_active', true)
                ->whereDate('end_date', '>=', CarbonImmutable::today('Asia/Damascus')))
            ->exists();

        if ($isUsed) {
            throw ValidationException::withMessages([
                'audio_track' => [$message],
            ]);
        }
    }
}
