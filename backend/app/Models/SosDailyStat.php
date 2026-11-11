<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SosDailyStat extends Model
{
    protected $fillable = ['date', 'opens_count'];

    protected $casts = [
        'date' => 'date',
        'opens_count' => 'integer',
    ];
}
