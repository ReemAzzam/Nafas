<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AudioStorageService
{
    public const DISK = 'local';

    public const FOLDER_TRACKS = 'audio-tracks';

    public function storeUpload(?UploadedFile $file, ?string $oldPath = null): ?string
    {
        if (! $file) {
            return $oldPath;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid().'.'.$extension;
        $path = $file->storeAs(self::FOLDER_TRACKS, $name, self::DISK);

        if (! $path) {
            throw new RuntimeException('تعذر حفظ الملف الصوتي.');
        }

        $this->delete($oldPath);

        return $path;
    }

    public function storeFixture(string $absolutePath, string $filename): string
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException("الملف الصوتي غير موجود أو غير قابل للقراءة: {$absolutePath}");
        }

        $relative = self::FOLDER_TRACKS.'/'.$filename;
        $stream = fopen($absolutePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("تعذر فتح الملف الصوتي: {$absolutePath}");
        }

        try {
            $stored = Storage::disk(self::DISK)->put($relative, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new RuntimeException("تعذر حفظ الملف الصوتي: {$filename}");
        }

        return $relative;
    }

    public function delete(?string $path): void
    {
        if (! $path || $this->isRemote($path)) {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    private function isRemote(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }
}
