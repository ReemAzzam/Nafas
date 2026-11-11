<?php

namespace App\Services;

use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArticleFavoriteService
{
    public function favorite(User $user, Article $article): void
    {
        $now = now();

        DB::table('article_favorites')->insertOrIgnore([
            'article_id' => $article->id,
            'user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function unfavorite(User $user, Article $article): void
    {
        $user->favoriteArticles()->detach($article->id);
    }
}
