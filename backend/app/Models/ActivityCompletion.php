<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityCompletion extends Model
{
    protected $fillable = [
        'user_id',
        'activity_type',
        'activity_id',
        'source',
        'plan_activity_id',
        'mood_log_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activity(): MorphTo
    {
        return $this->morphTo();
    }
}
