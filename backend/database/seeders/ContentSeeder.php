<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Exercise;
use App\Models\Factor;
use App\Models\Goal;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. إنشاء الأهداف (Goals)
        $goal1 = Goal::create(['title' => 'تخفيف القلق', 'icon' => 'anxiety.png']);
        $goal2 = Goal::create(['title' => 'تحسين النوم', 'icon' => 'sleep.png']);

        // 2. إنشاء طرق الدعم / العوامل (Support Methods / Factors)
        $factor1 = Factor::create(['title' => 'تمارين تنفس موجهة', 'icon' => 'breathing.png']);
        $factor2 = Factor::create(['title' => 'كتابة اليوميات (Journaling)', 'icon' => 'journaling.png']);
        $factor3 = Factor::create(['title' => 'مقالات وتوعية نفسية', 'icon' => 'articles.png']);

        // 3. إنشاء محتوى وهمي (Exercises & Articles)
        $ex1 = Exercise::create([
            'slug' => 'breathing-4-7-8',
            'title' => 'تنفس 4-7-8',
            'description' => 'تمرين للاسترخاء وتقليل التوتر',
            'type' => 'breathing',
            'duration_minutes' => 5,
        ]);

        $ex2 = Exercise::create([
            'slug' => 'morning-meditation',
            'title' => 'التأمل الصباحي',
            'description' => 'تأمل لزيادة التركيز والهدوء',
            'type' => 'relaxation',
            'duration_minutes' => 10,
        ]);

        $art1 = Article::create([
            'title' => 'كيف تتغلب على الأرق',
            'slug' => 'overcome-insomnia',
            'summary' => 'ملخص الشرح والنصائح الخاصة بالنوم...',
            'content' => 'المقال الكامل هنا...',
        ]);

        // 4. ربط المحتوى بالأهداف (Pivot Tables)
        $goal1->exercises()->attach([$ex1->id, $ex2->id]);
        $goal2->articles()->attach([$art1->id]);
    }
}
