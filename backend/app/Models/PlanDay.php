<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanDay extends Model
{
    protected $fillable = ['wellness_plan_id', 'day_number', 'date', 'is_completed'];

    protected $casts = [
        'date' => 'date',
        'is_completed' => 'boolean',
    ];

    public function wellnessPlan()
    {
        return $this->belongsTo(WellnessPlan::class);
    }

    public function activities()
    {
        return $this->hasMany(PlanActivity::class);
    }
}
