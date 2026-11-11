<?php

namespace App\Services\Admin;

use App\Models\SosCounselor;
use App\Models\SosHotline;
use App\Services\ImageStorageService;
use App\Support\SyrianPhone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class AdminSosService
{
    public function __construct(private ImageStorageService $images) {}

    public function saveCounselor(?SosCounselor $counselor, array $data, ?UploadedFile $image): SosCounselor
    {
        $counselor ??= new SosCounselor;

        if (isset($data['phone'])) {
            $data['phone'] = SyrianPhone::e164($data['phone']);
        }

        if ($image) {
            $counselor->image_url = $this->images->storeUpload(
                $image,
                ImageStorageService::FOLDER_SOS_COUNSELORS,
                $counselor->image_url,
            );
        } elseif ($data['remove_image'] ?? false) {
            $this->images->delete($counselor->image_url);
            $counselor->image_url = null;
        }

        $counselor->fill(Arr::except($data, ['image', 'remove_image']))->save();

        return $counselor->fresh();
    }

    public function deleteCounselor(SosCounselor $counselor): void
    {
        $this->images->delete($counselor->image_url);
        $counselor->delete();
    }

    public function saveHotline(?SosHotline $hotline, array $data): SosHotline
    {
        $hotline ??= new SosHotline;
        $hotline->fill($data)->save();

        return $hotline->fresh();
    }
}
