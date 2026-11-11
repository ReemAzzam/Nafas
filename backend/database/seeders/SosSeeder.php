<?php

namespace Database\Seeders;

use App\Models\SosCounselor;
use App\Models\SosHotline;
use App\Services\ImageStorageService;
use Illuminate\Database\Seeder;

class SosSeeder extends Seeder
{
    public function run(): void
    {
        $images = app(ImageStorageService::class);

        SosHotline::query()->firstOrCreate(
            ['slug' => 'police'],
            ['title' => 'الشرطة', 'phone' => '112', 'icon' => '🚔', 'sort_order' => 1, 'is_active' => true]
        );

        SosHotline::query()->firstOrCreate(
            ['slug' => 'ambulance'],
            ['title' => 'الإسعاف', 'phone' => '110', 'icon' => '🚑', 'sort_order' => 2, 'is_active' => true]
        );

        SosHotline::query()->firstOrCreate(
            ['slug' => 'red-crescent-jaramana-1'],
            ['title' => 'الهلال الأحمر العربي السوري - جرمانا', 'phone' => '0952133133', 'icon' => '🚑', 'sort_order' => 3, 'is_active' => true]
        );

        SosHotline::query()->firstOrCreate(
            ['slug' => 'red-crescent-jaramana-2'],
            ['title' => 'الهلال الأحمر العربي السوري - جرمانا', 'phone' => '0941133133', 'icon' => '🚑', 'sort_order' => 4, 'is_active' => true]
        );

        SosCounselor::query()->firstOrCreate(
            ['phone' => '+963956415611'],
            [
                'name' => 'د. جميل ركاب',
                'specialty' => 'طبيب نفسي ومدرب صحة عقلية ودعم نفسي اجتماعي',
                'address' => 'جرمانا - عيادات جرمانا التخصصية',
                'city' => 'جرمانا',
                'lat' => 33.4893138,
                'lng' => 36.3476465,
                'image_url' => $this->storeCounselorImage($images, 'jamil-rakkap.jpg'),
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        SosCounselor::query()->firstOrCreate(
            ['phone' => '+963934144613'],
            [
                'name' => 'د. جويل فهد',
                'specialty' => 'أخصائية ومعالجة نفسية',
                'address' => 'جرمانا - ساحة الحمصي',
                'city' => 'جرمانا',
                'lat' => 33.4825251,
                'lng' => 36.3507664,
                'image_url' => $this->storeCounselorImage($images, 'joelle-fahd.jpg'),
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        SosCounselor::query()->firstOrCreate(
            ['phone' => '+963996281012'],
            [
                'name' => 'د. مرح عزقول',
                'specialty' => 'طبيبة اختصاصية في علم النفس والسلوك',
                'address' => 'جرمانا - عيادات جرمانا التخصصية',
                'city' => 'جرمانا',
                'lat' => 33.4893138,
                'lng' => 36.3476465,
                'image_url' => $this->storeCounselorImage($images, 'marah-azqoul.jpg'),
                'sort_order' => 3,
                'is_active' => true,
            ]
        );
    }

    private function storeCounselorImage(ImageStorageService $images, string $filename): ?string
    {
        $source = database_path('seeders/fixtures/images/sos-counselors/'.$filename);

        if (! is_file($source)) {
            return null;
        }

        return $images->storeFixture(
            $source,
            ImageStorageService::FOLDER_SOS_COUNSELORS,
            $filename
        );
    }
}
