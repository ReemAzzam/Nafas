<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AudioStreamService
{
    public function stream(
        Request $request,
        string $path,
        string $mimeType,
        string $filename,
    ): Response {
        $disk = Storage::disk(AudioStorageService::DISK);

        abort_unless($disk->exists($path), 404);

        $absolutePath = $disk->path($path);
        $size = filesize($absolutePath);

        abort_if($size === false || $size < 1, 404);

        $range = $this->resolveRange($request->header('Range'), $size);

        if ($range === false) {
            return response('', 416, [
                'Accept-Ranges' => 'bytes',
                'Content-Range' => "bytes */{$size}",
            ]);
        }

        [$start, $end] = $range ?? [0, $size - 1];
        $length = $end - $start + 1;
        $status = $range === null ? 200 : 206;
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) $length,
            'Content-Type' => $mimeType,
        ];

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(
            function () use ($absolutePath, $start, $length): void {
                $handle = fopen($absolutePath, 'rb');

                if ($handle === false) {
                    return;
                }

                try {
                    fseek($handle, $start);
                    $remaining = $length;

                    while ($remaining > 0 && ! feof($handle)) {
                        $chunk = fread($handle, min(64 * 1024, $remaining));

                        if ($chunk === false || $chunk === '') {
                            break;
                        }

                        echo $chunk;
                        $remaining -= strlen($chunk);
                    }
                } finally {
                    fclose($handle);
                }
            },
            $status,
            $headers,
        );
    }

    /**
     * @return array{0: int, 1: int}|null|false
     */
    private function resolveRange(?string $header, int $size): array|null|false
    {
        if (! $header) {
            return null;
        }

        if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches)) {
            return false;
        }

        if ($matches[1] === '' && $matches[2] === '') {
            return false;
        }

        if ($matches[1] === '') {
            $suffixLength = (int) $matches[2];

            if ($suffixLength < 1) {
                return false;
            }

            return [max(0, $size - $suffixLength), $size - 1];
        }

        $start = (int) $matches[1];
        $end = $matches[2] === ''
            ? $size - 1
            : min((int) $matches[2], $size - 1);

        if ($start >= $size || $end < $start) {
            return false;
        }

        return [$start, $end];
    }
}
