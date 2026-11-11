<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Content\CompleteActivityRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\ExerciseResource;
use App\Models\Article;
use App\Models\Exercise;
use App\Services\ActivityCompletionService;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function __construct(private ActivityCompletionService $completions) {}

    public function exercises(Request $request)
    {
        $exercises = Exercise::query()
            ->where('is_published', true)
            ->with(['optionalAudioTrack' => fn ($query) => $query->where('is_published', true)])
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ExerciseResource::collection($exercises),
        ]);
    }

    public function showExercise(Exercise $exercise)
    {
        abort_unless($exercise->is_published, 404);
        $exercise->load([
            'optionalAudioTrack' => fn ($query) => $query->where('is_published', true),
        ]);

        return response()->json([
            'success' => true,
            'exercise' => ExerciseResource::make($exercise),
        ]);
    }

    public function articles(Request $request)
    {
        $user = $request->user();

        $articles = Article::query()
            ->where('is_published', true)
            ->with([
                'favoriteUsers' => fn ($query) => $query->whereKey($user->id),
            ])
            ->orderByDesc('id')
            ->get(['id', 'title', 'slug', 'summary', 'author_name', 'image_url']);

        return response()->json([
            'success' => true,
            'data' => ArticleResource::collection($articles),
        ]);
    }

    public function showArticle(Request $request, Article $article)
    {
        abort_unless($article->is_published, 404);

        $article->load([
            'favoriteUsers' => fn ($query) => $query->whereKey($request->user()->id),
        ]);

        return response()->json([
            'success' => true,
            'article' => ArticleResource::make($article),
        ]);
    }

    public function completeExercise(CompleteActivityRequest $request, Exercise $exercise)
    {
        abort_unless($exercise->is_published, 404);

        return $this->complete($request, $exercise);
    }

    public function completeArticle(CompleteActivityRequest $request, Article $article)
    {
        abort_unless($article->is_published, 404);

        return $this->complete($request, $article);
    }

    private function complete(CompleteActivityRequest $request, $activity)
    {
        $result = $this->completions->complete(
            $request->user(),
            $activity,
            $request->validated('plan_activity_id') ?? null,
            $request->validated('mood_log_id') ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'أحسنت، تم حفظ إنجازك.',
            'plan_progress' => $result['plan_progress'],
        ]);
    }
}
