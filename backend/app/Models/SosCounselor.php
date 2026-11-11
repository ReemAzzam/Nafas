<?php

namespace App\Models;

use App\Support\SyrianPhone;
use Illuminate\Database\Eloquent\Model;

class SosCounselor extends Model
{
    protected $fillable = [
        'name', 'specialty', 'phone', 'image_url',
        'address', 'city', 'lat', 'lng', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getTelUriAttribute(): string
    {
        return SyrianPhone::telUri($this->phone);
    }

    public function getMapsUrlAttribute(): string
    {
        return 'https://maps.google.com/?q='.$this->lat.','.$this->lng;
    }
}
