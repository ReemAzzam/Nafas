<?php

namespace App\Models;

use App\Support\SyrianPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosContact extends Model
{
    protected $fillable = ['name', 'phone'];

    protected $casts = [
        'phone' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTelUriAttribute(): string
    {
        return SyrianPhone::telUri($this->phone);
    }
}
