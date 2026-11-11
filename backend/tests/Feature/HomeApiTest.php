<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Exercise;
use App\Models\Factor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_home_returns_one_aggregated_payload_for_the_user(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-10-04 12:00', 'Asia/Damascus')
        );

        $user = User::factory()->create([
            'display_name' => 'لينا',
            'onboarding_status' => 'plan_ready',
        ]);
        $exercise = $this->exercise('calm-breathing');
        $article = Article::query()->create([
            'title' => 'مقال اليوم',
            'slug' => 'today-article',
            'summary' => 'ملخص قصير',
            'content' => 'المحتوى الكامل',
            'author_name' => 'فريق نفس',
            'is_published' => true,
        ]);
        $user->favoriteArticles()->attach($article->id);

        $factor = Factor::query()->create([
            'slug' => 'future-anxiety',
            'title' => 'القلق من المستقبل',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $moodLog = $user->moodLogs()->create([
            'mood_score' => 4,
            'suggestion_type' => 'exercise',
            'suggested_exercise_id' => $exercise->id,
            'dismissed_suggestions' => [],
        ]);
        $moodLog->factors()->attach($factor->id);

        $plan = $user->wellnessPlans()->create([
            'title' => 'خطتي الأسبوعية',
            'start_date' => '2026-10-03',
            'end_date' => '2026-10-09',
            'is_active' => true,
        ]);
        $day = $plan->days()->create([
            'day_number' => 2,
            'date' => '2026-10-04',
            'is_completed' => false,
        ]);
        $day->activities()->create([
            'activity_type' => Exercise::class,
            'activity_id' => $exercise->id,
            'is_completed' => false,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.display_name', 'لينا')
            ->assertJsonPath('user.onboarding_status', 'plan_ready')
            ->assertJsonPath('mood.has_checked_in_today', true)
            ->assertJsonPath('mood.latest.id', $moodLog->id)
            ->assertJsonPath('mood.latest.factors.0.slug', 'future-anxiety')
            ->assertJsonPath('current_plan.id', $plan->id)
            ->assertJsonPath('current_plan.current_day', 2)
            ->assertJsonPath('quick_support.calming_exercise.slug', 'calm-breathing')
            ->assertJsonPath('quick_support.featured_article.slug', 'today-article')
            ->assertJsonPath('quick_support.featured_article.is_favorite', true)
            ->assertJsonMissingPath('quick_support.featured_article.content');
    }

    public function test_home_returns_safe_nulls_when_optional_data_does_not_exist(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/home')
            ->assertOk()
            ->assertJsonPath('mood.has_checked_in_today', false)
            ->assertJsonPath('mood.latest', null)
            ->assertJsonPath('current_plan', null)
            ->assertJsonPath('quick_support.calming_exercise', null)
            ->assertJsonPath('quick_support.featured_article', null);
    }

    public function test_guest_and_blocked_user_cannot_access_home(): void
    {
        $this->get('/api/v1/home')->assertUnauthorized();

        $blocked = User::factory()->create(['is_blocked' => true]);
        Sanctum::actingAs($blocked);

        $this->getJson('/api/v1/home')->assertForbidden();
    }

    private function exercise(string $slug): Exercise
    {
        return Exercise::query()->create([
            'slug' => $slug,
            'title' => 'تنفس هادئ',
            'description' => 'تمرين تنفس قصير',
            'type' => 'breathing',
            'duration_minutes' => 2,
            'content_steps' => [],
        ]);
    }
}
