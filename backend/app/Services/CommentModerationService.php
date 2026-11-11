<?php

namespace App\Services;

use App\Models\ArticleComment;
use App\Models\User;

class CommentModerationService
{
    public function applyAiResult(
        ArticleComment $comment,
        string $status,
        ?float $score = null,
        array $labels = [],
    ): ArticleComment {
        $comment->update([
            'status' => $status === 'rejected' ? ArticleComment::STATUS_HIDDEN : $comment->status,
            'moderation_status' => $status,
            'moderation_score' => $score,
            'moderation_labels' => $labels,
            'moderated_at' => now(),
        ]);

        return $comment->fresh();
    }

    public function hide(ArticleComment $comment, User $admin, ?string $note = null): ArticleComment
    {
        $comment->update([
            'status' => ArticleComment::STATUS_HIDDEN,
            'moderation_status' => 'rejected',
            'moderated_at' => now(),
        ]);

        $comment->reports()->where('status', 'pending')->update([
            'status' => 'resolved',
            'resolved_by' => $admin->id,
            'admin_note' => $note,
            'resolved_at' => now(),
        ]);

        return $comment->fresh();
    }

    public function restore(ArticleComment $comment): ArticleComment
    {
        $comment->update([
            'status' => ArticleComment::STATUS_VISIBLE,
            'moderation_status' => 'approved',
            'moderated_at' => now(),
        ]);

        return $comment->fresh();
    }
}
