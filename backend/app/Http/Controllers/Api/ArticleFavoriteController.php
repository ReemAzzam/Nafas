<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Services\ArticleFavoriteService;
use Illuminate\Http\Request;

class ArticleFavoriteController extends Controller
{
    public function __construct(private ArticleFavoriteService $favorites) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $articles = $user->favoriteArticles()
            ->where('is_published', true)
            ->with([
                'favoriteUsers' => fn ($query) => $query->whereKey($user->id),
            ])
            ->orderByPivot('created_at', 'desc')
            ->paginate(20);

        return ArticleResource::collection($articles)
            ->additional(['success' => true]);
    }

    public function store(Request $request, Article $article)
    {
        $this->ensurePublished($article);
        $this->favorites->favorite($request->user(), $article);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ المقال في المفضلة.',
            'is_favorite' => true,
        ]);
    }

    public function destroy(Request $request, Article $article)
    {
        $this->ensurePublished($article);
        $this->favorites->unfavorite($request->user(), $article);

        return response()->json([
            'success' => true,
            'message' => 'تمت إزالة المقال من المفضلة.',
            'is_favorite' => false,
        ]);
    }

    private function ensurePublished(Article $article): void
    {
        abort_unless($article->is_published, 404);
    }
}
