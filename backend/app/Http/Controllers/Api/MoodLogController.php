<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mood\StoreMoodLogRequest;
use App\Http\Resources\FactorResource;
use App\Http\Resources\MoodLogResource;
use App\Http\Resources\SuggestionResource;
use App\Models\Factor;
use App\Models\MoodLog;
use App\Services\MoodSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MoodLogController extends Controller
{
    public function __construct(private MoodSuggestionService $suggestions) {}

    public function factors()
    {
        $factors = Factor::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'hint' => 'يمكنك المتابعة بدون اختيار.',
            'data' => FactorResource::collection($factors),
        ]);
    }

    public function store(StoreMoodLogRequest $request)
    {
        $user = $request->user();
        $moodScore = (int) $request->validated('mood_score');
        $factorIds = $request->validated('factor_ids') ?? [];

        $log = DB::transaction(function () use ($user, $moodScore, $factorIds) {
            $log = $user->moodLogs()->create([
                'mood_score' => $moodScore,
                'dismissed_suggestions' => [],
            ]);

            $log->factors()->sync($factorIds);

            $this->applySuggestion($log, $user, []);

            return $log;
        });

        $log->load(['factors', 'suggestedExercise', 'suggestedArticle']);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ شعورك.',
            'mood_log' => MoodLogResource::make($log),
        ], 201);
    }

    public function nextSuggestion(Request $request, MoodLog $moodLog)
    {
        abort_unless($moodLog->user_id === $request->user()->id, 404);

        $exclude = collect($moodLog->dismissed_suggestions ?? [])
            ->when($moodLog->suggestionKey(), fn ($c, $key) => $c->push($key))
            ->unique()
            ->values()
            ->all();

        $next = $this->applySuggestion($moodLog, $request->user(), $exclude);
        $moodLog->load(['factors', 'suggestedExercise', 'suggestedArticle']);

        if (! $next) {
            return response()->json([
                'success' => true,
                'message' => 'لا يوجد خيار آخر حالياً. يمكنك العودة لاحقاً.',
                'mood_log' => MoodLogResource::make($moodLog),
                'suggestion' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'إليك خياراً آخر.',
            'mood_log' => MoodLogResource::make($moodLog),
            'suggestion' => SuggestionResource::make($next),
        ]);
    }

    public function latest(Request $request)
    {
        $log = $request->user()
            ->moodLogs()
            ->with(['factors', 'suggestedExercise', 'suggestedArticle'])
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'mood_log' => $log ? MoodLogResource::make($log) : null,
        ]);
    }

    private function applySuggestion(MoodLog $log, $user, array $exclude)
    {
        $factorSlugs = $log->factors()->pluck('factors.slug');
        $item = $this->suggestions->suggest($user, $log->mood_score, $factorSlugs, $exclude);

        $dismissed = $exclude;
        if ($item) {
            $dismissed[] = $this->suggestions->key($item);
        }

        $log->fill([
            'suggestion_type' => $item ? $this->suggestions->type($item) : null,
            'suggested_exercise_id' => $item && $this->suggestions->type($item) === 'exercise' ? $item->id : null,
            'suggested_article_id' => $item && $this->suggestions->type($item) === 'article' ? $item->id : null,
            'dismissed_suggestions' => array_values(array_unique($dismissed)),
        ])->save();

        return $item;
    }
}
