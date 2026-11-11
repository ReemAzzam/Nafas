<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Audio\ListAudioTracksRequest;
use App\Http\Requests\Api\Content\CompleteActivityRequest;
use App\Http\Resources\AudioTrackResource;
use App\Models\AudioTrack;
use App\Services\ActivityCompletionService;
use App\Services\AudioStreamService;
use Illuminate\Http\Request;

class AudioTrackController extends Controller
{
    public function __construct(
        private ActivityCompletionService $completions,
        private AudioStreamService $streams,
    ) {}

    public function index(ListAudioTracksRequest $request)
    {
        $tracks = AudioTrack::query()
            ->where('is_published', true)
            ->when(
                $request->validated('category'),
                fn ($query, $category) => $query->where('category', $category)
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => AudioTrackResource::collection($tracks),
        ]);
    }

    public function show(AudioTrack $audioTrack)
    {
        abort_unless($audioTrack->is_published, 404);

        return response()->json([
            'success' => true,
            'audio_track' => AudioTrackResource::make($audioTrack),
        ]);
    }

    public function complete(CompleteActivityRequest $request, AudioTrack $audioTrack)
    {
        abort_unless($audioTrack->is_published, 404);

        $result = $this->completions->complete(
            $request->user(),
            $audioTrack,
            $request->validated('plan_activity_id') ?? null,
            $request->validated('mood_log_id') ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'أحسنت، تم حفظ استماعك.',
            'plan_progress' => $result['plan_progress'],
        ]);
    }

    public function stream(Request $request, AudioTrack $audioTrack)
    {
        abort_unless($audioTrack->is_published, 404);

        return $this->streams->stream(
            $request,
            $audioTrack->audio_path,
            $audioTrack->mime_type,
            basename($audioTrack->audio_path),
        );
    }
}
