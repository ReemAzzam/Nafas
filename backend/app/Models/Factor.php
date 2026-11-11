<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Factor extends Model
{
    protected $fillable = ['title', 'slug', 'icon', 'is_active', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_factors')->withTimestamps();
    }

    public function moodLogs()
    {
        return $this->belongsToMany(MoodLog::class, 'mood_log_factor')->withTimestamps();
    }
}
