<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SosHotlineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'phone' => $this->phone,
            'tel_uri' => 'tel:'.$this->phone,
            'icon' => $this->icon,
        ];
    }
}
