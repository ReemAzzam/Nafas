<?php

namespace App\Http\Resources;

use App\Services\ImageStorageService;
use App\Support\SyrianPhone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SosCounselorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $e164 = SyrianPhone::e164($this->phone);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'specialty' => $this->specialty,
            'phone' => $e164,
            'tel_uri' => SyrianPhone::telUri($this->phone),
            'image_url' => app(ImageStorageService::class)->url($this->image_url),
            'address' => $this->address,
            'city' => $this->city,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'maps_url' => 'https://maps.google.com/?q='.$this->lat.','.$this->lng,
        ];
    }
}
