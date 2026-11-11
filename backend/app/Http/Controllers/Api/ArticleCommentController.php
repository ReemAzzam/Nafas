<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ArticleComment\ReportArticleCommentRequest;
use App\Http\Requests\Api\ArticleComment\StoreArticleCommentRequest;
use App\Http\Resources\ArticleCommentResource;
use App\Models\Article;
use App\Models\ArticleComment;
use App\Services\ArticleCommentService;
use Illuminate\Http\Request;

class ArticleCommentController extends Controller
{
    public function __construct(private ArticleCommentService $comments) {}

    public function index(Request $request, Article $article)
    {
        $this->ensurePublished($article);

        return ArticleCommentResource::collection(
            $this->comments->paginate($article, $request->user())
        )->additional(['success' => true]);
    }

    public function store(StoreArticleCommentRequest $request, Article $article)
    {
        $this->ensurePublished($article);

        $comment = $this->comments->create(
            $article,
            $request->user(),
            $request->validated('body')
        );

        return response()->json([
            'success' => true,
            'message' => 'تم نشر تعليقك.',
            'comment' => ArticleCommentResource::make($comment),
        ], 201);
    }

    public function destroy(Request $request, Article $article, ArticleComment $comment)
    {
        $this->ensurePublished($article);
        $this->comments->delete($article, $comment, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'تم حذف تعليقك.',
        ]);
    }

    public function like(Request $request, ArticleComment $comment)
    {
        $this->ensureVisible($comment);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل إعجابك.',
            'is_liked' => true,
            'likes_count' => $this->comments->like($comment, $request->user()),
        ]);
    }

    public function unlike(Request $request, ArticleComment $comment)
    {
        $this->ensureVisible($comment);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء إعجابك.',
            'is_liked' => false,
            'likes_count' => $this->comments->unlike($comment, $request->user()),
        ]);
    }

    public function report(ReportArticleCommentRequest $request, ArticleComment $comment)
    {
        $this->ensureVisible($comment);

        $created = $this->comments->report(
            $comment,
            $request->user(),
            $request->validated('reason')
        );

        return response()->json([
            'success' => true,
            'message' => $created
                ? 'تم إرسال البلاغ للمراجعة.'
                : 'سبق أن أرسلت بلاغًا عن هذا التعليق.',
        ], $created ? 201 : 200);
    }

    private function ensurePublished(Article $article): void
    {
        abort_unless($article->is_published, 404);
    }

    private function ensureVisible(ArticleComment $comment): void
    {
        abort_unless(
            $comment->status === ArticleComment::STATUS_VISIBLE
                && $comment->article()->where('is_published', true)->exists(),
            404
        );
    }
}
