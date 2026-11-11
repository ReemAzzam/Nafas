<?php

namespace Database\Seeders;

use App\Models\Factor;
use Illuminate\Database\Seeder;

class FactorSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['slug' => 'study_work',       'title' => 'دراسة أو عمل',       'icon' => '📖', 'sort_order' => 1],
            ['slug' => 'overthinking',     'title' => 'كثرة تفكير',          'icon' => '☁️', 'sort_order' => 2],
            ['slug' => 'relationships',    'title' => 'علاقات',              'icon' => '💗', 'sort_order' => 3],
            ['slug' => 'family',           'title' => 'عائلة',               'icon' => '🏠', 'sort_order' => 4],
            ['slug' => 'social_media',     'title' => 'وسائل التواصل',       'icon' => '📱', 'sort_order' => 5],
            ['slug' => 'boredom',          'title' => 'الملل أو الفراغ',     'icon' => '🕰', 'sort_order' => 6],
            ['slug' => 'future_anxiety',   'title' => 'القلق من المستقبل',   'icon' => '🌅', 'sort_order' => 7],
            ['slug' => 'procrastination',  'title' => 'التسويف',             'icon' => '🎯', 'sort_order' => 8],
            ['slug' => 'restless_sleep',   'title' => 'نوم مضطرب',           'icon' => '🌙', 'sort_order' => 9],
        ];

        foreach ($items as $item) {
            Factor::query()->updateOrCreate(
                ['slug' => $item['slug']],
                $item + ['is_active' => true]
            );
        }
    }
}
