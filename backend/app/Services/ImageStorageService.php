<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageStorageService
{
    public const DISK = 'public';

    public const FOLDER_EXERCISES = 'exercises';

    public const FOLDER_ARTICLES = 'articles';

    public const FOLDER_SOS_COUNSELORS = 'sos-counselors';

    public const FOLDER_PROFILES = 'profiles';

    public const FOLDER_AUDIO_TRACK_COVERS = 'audio-tracks/covers';

    public function storeUpload(?UploadedFile $file, string $folder, ?string $oldPath = null): ?string
    {
        if (! $file) {
            return $oldPath;
        }

        $name = Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs($folder, $name, self::DISK);

        $this->delete($oldPath);

        return $path;
    }

    public function storeFixture(string $absolutePath, string $folder, string $filename): string
    {
        $relative = $folder.'/'.$filename;

        Storage::disk(self::DISK)->put(
            $relative,
            file_get_contents($absolutePath)
        );

        return $relative;
    }

    public function delete(?string $path): void
    {
        if (! $path || $this->isRemote($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public function url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }
        $path = trim($path);
        if (str_starts_with($path, '//')) return 'https:'.$path;

        if ($this->isRemote($path)) {
            return $path;
        }

        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#^/?(?:public/)?storage/app/public/#', '', $path);
        $path = preg_replace('#^/?(?:public/)?storage/#', '', $path);
        $path = preg_replace('#^/?public/#', '', $path);

        return Storage::disk(self::DISK)->url(ltrim($path, '/'));
    }

    private function isRemote(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }
}
