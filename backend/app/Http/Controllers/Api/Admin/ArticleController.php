<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpsertArticleRequest;
use App\Http\Resources\Admin\AdminArticleResource;
use App\Models\Article;
use App\Services\Admin\AdminContentService;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function __construct(private AdminContentService $content) {}

    public function index(Request $request)
    {
        $articles = match ($request->query('status')) {
            'deleted' => Article::onlyTrashed(),
            'draft' => Article::query()->where('is_published', false),
            'published' => Article::query()->where('is_published', true),
            default => Article::query(),
        };

        return AdminArticleResource::collection(
            $articles
                ->when($request->query('search'), fn ($query, $search) => $query
                    ->where(fn ($query) => $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")))
                ->with('goals:id,title,icon')
                ->withCount(['comments', 'favoriteUsers'])
                ->latest('id')
                ->paginate(max(1, min((int) $request->query('per_page', 20), 100)))
        )->additional(['success' => true]);
    }

    public function store(UpsertArticleRequest $request)
    {
        $article = $this->content->saveArticle(
            null,
            $request->validated(),
            $request->file('image'),
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء المقال بنجاح.',
            'article' => AdminArticleResource::make($article),
        ], 201);
    }

    public function show(int $article)
    {
        $model = Article::withTrashed()->findOrFail($article);

        return response()->json([
            'success' => true,
            'article' => AdminArticleResource::make($this->content->prepareArticle($model)),
        ]);
    }

    public function update(UpsertArticleRequest $request, int $article)
    {
        $model = Article::query()->findOrFail($article);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المقال بنجاح.',
            'article' => AdminArticleResource::make(
                $this->content->saveArticle($model, $request->validated(), $request->file('image'))
            ),
        ]);
    }

    public function destroy(int $article)
    {
        $this->content->deleteArticle(Article::query()->findOrFail($article));

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المقال بنجاح.',
        ]);
    }

    public function publish(int $article)
    {
        $model = Article::query()->findOrFail($article);
        $model->update(['is_published' => true]);

        return response()->json([
            'success' => true,
            'message' => 'تم نشر المقال بنجاح.',
            'article' => AdminArticleResource::make($this->content->prepareArticle($model)),
        ]);
    }

    public function unpublish(int $article)
    {
        $model = Article::query()->findOrFail($article);
        $model->update(['is_published' => false]);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء نشر المقال بنجاح.',
            'article' => AdminArticleResource::make($this->content->prepareArticle($model)),
        ]);
    }
}
