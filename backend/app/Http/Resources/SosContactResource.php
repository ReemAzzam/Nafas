<?php

namespace App\Http\Resources;

use App\Support\SyrianPhone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SosContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => SyrianPhone::e164($this->phone),
            'tel_uri' => SyrianPhone::telUri($this->phone),
        ];
    }
}
