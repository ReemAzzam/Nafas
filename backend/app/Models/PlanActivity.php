<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanActivity extends Model
{
    protected $fillable = ['plan_day_id', 'activity_type', 'activity_id', 'is_completed'];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    public function planDay()
    {
        return $this->belongsTo(PlanDay::class);
    }

    public function activity()
    {
        return $this->morphTo();
    }
}
