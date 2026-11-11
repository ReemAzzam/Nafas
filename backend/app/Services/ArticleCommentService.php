<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ArticleCommentService
{
    public function paginate(Article $article, User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $article->comments()
            ->where('status', ArticleComment::STATUS_VISIBLE)
            ->with([
                'user:id,display_name',
                'likes' => fn ($query) => $query->whereKey($user->id),
            ])
            ->withCount('likes')
            ->latest()
            ->paginate($perPage);
    }

    public function create(Article $article, User $user, string $body): ArticleComment
    {
        $comment = $article->comments()->create([
            'user_id' => $user->id,
            'body' => $body,
            'status' => ArticleComment::STATUS_VISIBLE,
        ]);

        return $this->prepare($comment, $user);
    }

    public function delete(Article $article, ArticleComment $comment, User $user): void
    {
        abort_unless(
            $comment->article_id === $article->id && $comment->user_id === $user->id,
            404
        );

        $comment->delete();
    }

    public function like(ArticleComment $comment, User $user): int
    {
        $now = now();

        DB::table('article_comment_likes')->insertOrIgnore([
            'article_comment_id' => $comment->id,
            'user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $comment->likes()->count();
    }

    public function unlike(ArticleComment $comment, User $user): int
    {
        $comment->likes()->detach($user->id);

        return $comment->likes()->count();
    }

    public function report(ArticleComment $comment, User $user, ?string $reason): bool
    {
        $report = $comment->reports()->firstOrCreate(
            ['user_id' => $user->id],
            ['reason' => $reason]
        );

        if ($report->wasRecentlyCreated && $comment->moderation_status !== 'rejected') {
            $comment->update(['moderation_status' => 'needs_review']);
        }

        return $report->wasRecentlyCreated;
    }

    private function prepare(ArticleComment $comment, User $user): ArticleComment
    {
        $comment->load([
            'user:id,display_name',
            'likes' => fn ($query) => $query->whereKey($user->id),
        ])->loadCount('likes');

        return $comment;
    }
}
