<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\Exercise;
use App\Services\ImageStorageService;
use App\Services\SosCalmingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminContentService
{
    public function __construct(private ImageStorageService $images) {}

    public function saveArticle(?Article $article, array $data, ?UploadedFile $image): Article
    {
        return DB::transaction(function () use ($article, $data, $image) {
            $article ??= new Article;
            $this->applyImage($article, $data, $image, ImageStorageService::FOLDER_ARTICLES);
            $article->fill(Arr::except($data, ['goal_ids', 'image', 'remove_image']))->save();

            if (array_key_exists('goal_ids', $data)) {
                $article->goals()->sync($data['goal_ids']);
            }

            return $this->prepareArticle($article);
        });
    }

    public function saveExercise(?Exercise $exercise, array $data, ?UploadedFile $image): Exercise
    {
        return DB::transaction(function () use ($exercise, $data, $image) {
            $exercise ??= new Exercise;
            $this->applyImage($exercise, $data, $image, ImageStorageService::FOLDER_EXERCISES);
            $exercise->fill(Arr::except($data, ['goal_ids', 'image', 'remove_image']))->save();

            if (array_key_exists('goal_ids', $data)) {
                $exercise->goals()->sync($data['goal_ids']);
            }

            return $this->prepareExercise($exercise);
        });
    }

    public function deleteArticle(Article $article): void
    {
        $article->delete();
    }

    public function deleteExercise(Exercise $exercise): void
    {
        if (in_array($exercise->slug, SosCalmingService::EXERCISE_SLUGS, true)) {
            throw ValidationException::withMessages([
                'exercise' => ['لا يمكن حذف تمرين مرتبط بوضع SOS. يمكن تعديله فقط.'],
            ]);
        }

        $exercise->delete();
    }

    public function prepareArticle(Article $article): Article
    {
        return $article->load('goals:id,title,icon')
            ->loadCount(['comments', 'favoriteUsers']);
    }

    public function prepareExercise(Exercise $exercise): Exercise
    {
        return $exercise->load([
            'goals:id,title,icon',
            'optionalAudioTrack',
        ]);
    }

    private function applyImage(Model $model, array $data, ?UploadedFile $image, string $folder): void
    {
        if ($image) {
            $model->image_url = $this->images->storeUpload($image, $folder, $model->image_url);

            return;
        }

        if ($data['remove_image'] ?? false) {
            $this->images->delete($model->image_url);
            $model->image_url = null;
        }
    }
}
