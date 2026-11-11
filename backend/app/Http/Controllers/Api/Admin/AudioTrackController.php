<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexAudioTracksRequest;
use App\Http\Requests\Api\Admin\UpsertAudioTrackRequest;
use App\Http\Resources\Admin\AdminAudioTrackResource;
use App\Models\AudioTrack;
use App\Services\Admin\AdminAudioTrackService;
use App\Services\AudioStreamService;
use Illuminate\Http\Request;

class AudioTrackController extends Controller
{
    public function __construct(
        private AdminAudioTrackService $audioTracks,
        private AudioStreamService $streams,
    ) {}

    public function index(IndexAudioTracksRequest $request)
    {
        $filters = $request->validated();
        $tracks = match ($filters['status'] ?? null) {
            'deleted' => AudioTrack::onlyTrashed(),
            'draft' => AudioTrack::query()->where('is_published', false),
            'published' => AudioTrack::query()->where('is_published', true),
            default => AudioTrack::query(),
        };

        return AdminAudioTrackResource::collection(
            $tracks
                ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
                ->when($filters['search'] ?? null, fn ($query, $search) => $query
                    ->where(fn ($query) => $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")))
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->paginate($filters['per_page'] ?? 20)
        )->additional(['success' => true]);
    }

    public function store(UpsertAudioTrackRequest $request)
    {
        $track = $this->audioTracks->save(
            null,
            $request->validated(),
            $request->file('audio'),
            $request->file('image'),
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المقطع الصوتي كمسودة.',
            'audio_track' => AdminAudioTrackResource::make($track),
        ], 201);
    }

    public function show(int $audioTrack)
    {
        $track = AudioTrack::withTrashed()->findOrFail($audioTrack);

        return response()->json([
            'success' => true,
            'audio_track' => AdminAudioTrackResource::make($track),
        ]);
    }

    public function preview(Request $request, int $audioTrack)
    {
        $track = AudioTrack::withTrashed()->findOrFail($audioTrack);

        abort_unless($track->audio_path, 404);

        return $this->streams->stream(
            $request,
            $track->audio_path,
            $track->mime_type ?: 'audio/mpeg',
            basename($track->audio_path),
        );
    }

    public function update(UpsertAudioTrackRequest $request, int $audioTrack)
    {
        $track = AudioTrack::query()->findOrFail($audioTrack);
        $track = $this->audioTracks->save(
            $track,
            $request->validated(),
            $request->file('audio'),
            $request->file('image'),
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المقطع الصوتي بنجاح.',
            'audio_track' => AdminAudioTrackResource::make($track),
        ]);
    }

    public function destroy(int $audioTrack)
    {
        $this->audioTracks->delete(AudioTrack::query()->findOrFail($audioTrack));

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المقطع الصوتي بنجاح.',
        ]);
    }

    public function restore(int $audioTrack)
    {
        $track = AudioTrack::withTrashed()->findOrFail($audioTrack);

        return response()->json([
            'success' => true,
            'message' => 'تمت استعادة المقطع الصوتي كمسودة.',
            'audio_track' => AdminAudioTrackResource::make($this->audioTracks->restore($track)),
        ]);
    }

    public function publish(int $audioTrack)
    {
        $track = AudioTrack::query()->findOrFail($audioTrack);

        return response()->json([
            'success' => true,
            'message' => 'تم نشر المقطع الصوتي بنجاح.',
            'audio_track' => AdminAudioTrackResource::make($track),
        ]);
    }

    public function unpublish(int $audioTrack)
    {
        $track = AudioTrack::query()->findOrFail($audioTrack);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء نشر المقطع الصوتي بنجاح.',
            'audio_track' => AdminAudioTrackResource::make($track),
        ]);
    }
}