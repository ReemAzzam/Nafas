<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SosHotline extends Model
{
    protected $fillable = ['slug', 'title', 'phone', 'icon', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
