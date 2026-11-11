<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleCommentReport extends Model
{
    protected $fillable = [
        'article_comment_id',
        'user_id',
        'reason',
        'status',
        'resolved_by',
        'admin_note',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(ArticleComment::class, 'article_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
