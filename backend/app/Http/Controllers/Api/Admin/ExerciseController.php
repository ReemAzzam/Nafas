<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpsertExerciseRequest;
use App\Http\Resources\Admin\AdminExerciseResource;
use App\Models\Exercise;
use App\Services\Admin\AdminContentService;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function __construct(private AdminContentService $content) {}

    public function index(Request $request)
    {
        $exercises = match ($request->query('status')) {
            'deleted' => Exercise::onlyTrashed(),
            'draft' => Exercise::query()->where('is_published', false),
            'published' => Exercise::query()->where('is_published', true),
            default => Exercise::query(),
        };

        return AdminExerciseResource::collection(
            $exercises
                ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
                ->when($request->query('search'), fn ($query, $search) => $query
                    ->where(fn ($query) => $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")))
                ->with('goals:id,title,icon')
                ->with('optionalAudioTrack')
                ->latest('id')
                ->paginate(max(1, min((int) $request->query('per_page', 20), 100)))
        )->additional(['success' => true]);
    }

    public function store(UpsertExerciseRequest $request)
    {
        $exercise = $this->content->saveExercise(
            null,
            $request->validated(),
            $request->file('image'),
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء التمرين بنجاح.',
            'exercise' => AdminExerciseResource::make($exercise),
        ], 201);
    }

    public function show(int $exercise)
    {
        $model = Exercise::withTrashed()->findOrFail($exercise);

        return response()->json([
            'success' => true,
            'exercise' => AdminExerciseResource::make($this->content->prepareExercise($model)),
        ]);
    }

    public function update(UpsertExerciseRequest $request, int $exercise)
    {
        $model = Exercise::query()->findOrFail($exercise);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث التمرين بنجاح.',
            'exercise' => AdminExerciseResource::make(
                $this->content->saveExercise($model, $request->validated(), $request->file('image'))
            ),
        ]);
    }

    public function destroy(int $exercise)
    {
        $this->content->deleteExercise(Exercise::query()->findOrFail($exercise));

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التمرين بنجاح.',
        ]);
    }

    public function publish(int $exercise)
    {
        $model = Exercise::query()->findOrFail($exercise);
        $model->update(['is_published' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم نشر التمرين بنجاح.',
            'exercise' => AdminExerciseResource::make($this->content->prepareExercise($model)),
        ]);
    }

    public function unpublish(int $exercise)
    {
        $model = Exercise::query()->findOrFail($exercise);
        $model->update(['is_published' => false]);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء نشر التمرين بنجاح.',
            'exercise' => AdminExerciseResource::make($this->content->prepareExercise($model)),
        ]);
    }
}
