<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'content',
        'image_url',
        'author_name',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function goals()
    {
        return $this->belongsToMany(Goal::class, 'article_goal')->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ArticleComment::class);
    }

    public function favoriteUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_favorites')->withTimestamps();
    }

    // علاقة الـ Polymorphic مع أنشطة الخطة
    public function planActivities()
    {
        return $this->morphMany(PlanActivity::class, 'activity');
    }
}
