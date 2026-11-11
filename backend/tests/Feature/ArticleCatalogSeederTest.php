<?php

namespace Tests\Feature;

use App\Models\Article;
use Database\Seeders\NafasDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_adds_the_eighteen_new_articles_without_duplicates(): void
    {
        $this->seed(NafasDemoSeeder::class);
        $this->seed(NafasDemoSeeder::class);

        $this->assertSame(24, Article::query()->count());

        $newSlugs = [
            'when-we-lose-a-parent',
            'grief-comes-in-waves',
            'time-management-when-energy-is-low',
            'how-to-start-without-motivation',
            'procrastination-is-not-always-laziness',
            'focus-is-not-a-machine',
            'when-thinking-becomes-a-loop',
            'future-anxiety-in-uncertain-times',
            'you-are-more-than-your-results',
            'steady-your-sleep-rhythm',
            'when-social-comparison-feels-heavy',
            'family-conflict-without-losing-yourself',
            'healthy-relationships-do-not-erase-you',
            'after-a-relationship-ends',
            'boredom-is-not-always-an-empty-space',
            'emotional-eating-without-punishment',
            'low-mood-and-small-steps',
            'asking-for-help-is-not-a-last-resort',
        ];

        $this->assertSame(18, Article::query()->whereIn('slug', $newSlugs)->count());

        $parentLoss = Article::query()
            ->where('slug', 'when-we-lose-a-parent')
            ->with('goals')
            ->firstOrFail();

        $this->assertSame(7, substr_count($parentLoss->content, "\n") + 1);
        $this->assertEqualsCanonicalizing(
            ['مزاج أفضل', 'قلق أقل'],
            $parentLoss->goals->pluck('title')->all()
        );
    }
}
