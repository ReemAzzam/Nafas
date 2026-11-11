<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleComment extends Model
{
    public const STATUS_VISIBLE = 'visible';

    public const STATUS_HIDDEN = 'hidden';

    protected $fillable = [
        'article_id',
        'user_id',
        'body',
        'status',
        'moderation_status',
        'moderation_score',
        'moderation_labels',
        'moderated_at',
    ];

    protected $casts = [
        'moderation_score' => 'float',
        'moderation_labels' => 'array',
        'moderated_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_comment_likes')->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ArticleCommentReport::class);
    }
}
