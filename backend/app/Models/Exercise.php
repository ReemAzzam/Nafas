<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exercise extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'type',
        'duration_minutes',
        'content_steps',
        'media_type',
        'media_url',
        'media_credit',
        'image_url',
        'optional_audio_track_id',
        'is_published',
    ];

    protected $casts = [
        'content_steps' => 'array',
        'duration_minutes' => 'integer',
        'is_published' => 'boolean',
    ];

    public function moodLogs()
    {
        return $this->hasMany(MoodLog::class, 'suggested_exercise_id');
    }

    public function goals()
    {
        return $this->belongsToMany(Goal::class, 'goal_exercise')->withTimestamps();
    }

    // علاقة الـ Polymorphic مع أنشطة الخطة
    public function planActivities()
    {
        return $this->morphMany(PlanActivity::class, 'activity');
    }

    public function optionalAudioTrack(): BelongsTo
    {
        return $this->belongsTo(AudioTrack::class, 'optional_audio_track_id');
    }
}
