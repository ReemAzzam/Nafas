<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = ['user_id', 'score', 'severity_level', 'answers_payload'];

    protected $casts = [
        'answers_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
