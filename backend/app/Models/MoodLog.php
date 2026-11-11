<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoodLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mood_score',
        'suggestion_type',

        'suggested_article_id',
        'suggested_exercise_id',
        'dismissed_suggestions',
        'suggestion_completed_at',
    ];

    protected $casts = [
        'mood_score' => 'integer',
        'note' => 'encrypted', // تشفير ملاحظات المزاج حمايةً للخصوصية
        'dismissed_suggestions' => 'array',
        'suggestion_completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function factors()
    {
        return $this->belongsToMany(Factor::class, 'mood_log_factor')->withTimestamps();
    }

    public function suggestedExercise()
    {
        return $this->belongsTo(Exercise::class, 'suggested_exercise_id');
    }

    public function suggestedArticle()
    {
        return $this->belongsTo(Article::class, 'suggested_article_id');
    }

    public function currentSuggestion(): Exercise|Article|null
    {
        return $this->suggestion_type === 'article'
            ? $this->suggestedArticle
            : ($this->suggestion_type === 'exercise' ? $this->suggestedExercise : null);
    }

    public function suggestionKey(): ?string
    {
        if ($this->suggestion_type === 'article' && $this->suggested_article_id) {
            return 'article:'.$this->suggested_article_id;
        }
        if ($this->suggestion_type === 'exercise' && $this->suggested_exercise_id) {
            return 'exercise:'.$this->suggested_exercise_id;
        }

        return null;
    }
}
