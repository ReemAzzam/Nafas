<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AudioTrack extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'category',
        'duration_seconds',
        'audio_path',
        'image_url',
        'credit',
        'license_type',
        'license_source_url',
        'mime_type',
        'file_size_bytes',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'file_size_bytes' => 'integer',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function planActivities(): MorphMany
    {
        return $this->morphMany(PlanActivity::class, 'activity');
    }
}
