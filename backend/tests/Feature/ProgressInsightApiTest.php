<?php

namespace Tests\Feature;

use App\Models\ActivityCompletion;
use App\Models\Article;
use App\Models\Exercise;
use App\Models\Factor;
use App\Models\MoodLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProgressInsightApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_insights_aggregate_only_the_users_data_in_damascus_time(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $study = Factor::query()->create([
            'slug' => 'study-work',
            'title' => 'دراسة أو عمل',
            'icon' => '📖',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $sleep = Factor::query()->create([
            'slug' => 'sleep',
            'title' => 'نوم مضطرب',
            'icon' => '🌙',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $first = $this->moodLog($user, 3, '2026-10-03 00:30');
        $second = $this->moodLog($user, 5, '2026-10-03 12:00');
        $third = $this->moodLog($user, 4, '2026-10-04 09:00');
        $outside = $this->moodLog($user, 1, '2026-10-02 23:00');
        $this->moodLog($otherUser, 1, '2026-10-03 10:00');

        $first->factors()->attach($study->id);
        $second->factors()->attach($study->id);
        $third->factors()->attach($sleep->id);
        $outside->factors()->attach($sleep->id);

        $this->completion($user, Exercise::class, '2026-10-03 13:00');
        $this->completion($user, Exercise::class, '2026-10-04 10:00');
        $this->completion($user, Article::class, '2026-10-04 11:00');
        $this->completion($otherUser, Exercise::class, '2026-10-04 12:00');
        $this->journal($user, '2026-10-04 14:00');

        Sanctum::actingAs($user);

        $response = $this->getJson(
            '/api/v1/progress/insights?period=week&date=2026-10-04'
        );

        $response
            ->assertOk()
            ->assertJsonPath('period.start_date', '2026-10-03')
            ->assertJsonPath('period.end_date', '2026-10-09')
            ->assertJsonPath('period.timezone', 'Asia/Damascus')
            ->assertJsonPath('summary.mood_logs_count', 3)
            ->assertJsonPath('summary.average_mood_score', 4)
            ->assertJsonPath('summary.completed_activities_count', 3)
            ->assertJsonPath('summary.completed_exercises_count', 2)
            ->assertJsonPath('summary.completed_articles_count', 1)
            ->assertJsonPath('summary.journal_entries_count', 1)
            ->assertJsonPath('summary.active_days_count', 2)
            ->assertJsonCount(7, 'mood_chart')
            ->assertJsonPath('mood_chart.0.date', '2026-10-03')
            ->assertJsonPath('mood_chart.0.average_score', 4)
            ->assertJsonPath('mood_chart.0.logs_count', 2)
            ->assertJsonPath('mood_chart.1.date', '2026-10-04')
            ->assertJsonPath('mood_chart.1.average_score', 4)
            ->assertJsonPath('mood_chart.2.average_score', null)
            ->assertJsonPath('top_factors.0.slug', 'study-work')
            ->assertJsonPath('top_factors.0.count', 2)
            ->assertJsonPath('top_factors.1.slug', 'sleep')
            ->assertJsonPath('top_factors.1.count', 1);
    }

    public function test_monthly_insights_return_empty_neutral_values_when_no_data_exists(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/progress/insights?period=month&date=2026-02-10')
            ->assertOk()
            ->assertJsonPath('period.start_date', '2026-02-01')
            ->assertJsonPath('period.end_date', '2026-02-28')
            ->assertJsonPath('summary.mood_logs_count', 0)
            ->assertJsonPath('summary.average_mood_score', null)
            ->assertJsonPath('summary.completed_activities_count', 0)
            ->assertJsonPath('summary.journal_entries_count', 0)
            ->assertJsonPath('summary.active_days_count', 0)
            ->assertJsonCount(28, 'mood_chart')
            ->assertJsonCount(0, 'top_factors');
    }

    public function test_period_and_date_have_arabic_validation_errors(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/progress/insights?period=year')
            ->assertUnprocessable()
            ->assertJsonPath('errors.period.0', 'الفترة يجب أن تكون أسبوعًا أو شهرًا.');

        $this->getJson('/api/v1/progress/insights?period=week&date=04-10-2026')
            ->assertUnprocessable()
            ->assertJsonPath('errors.date.0', 'يجب أن يكون التاريخ بالصيغة YYYY-MM-DD.');
    }

    public function test_mood_suggestion_completion_is_idempotent_but_explore_can_repeat(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::query()->create([
            'slug' => 'test-mood-exercise',
            'title' => 'تمرين تجريبي',
            'description' => 'وصف التمرين',
            'type' => 'breathing',
            'duration_minutes' => 2,
            'content_steps' => [],
        ]);
        $moodLog = $user->moodLogs()->create([
            'mood_score' => 3,
            'suggestion_type' => 'exercise',
            'suggested_exercise_id' => $exercise->id,
            'dismissed_suggestions' => [],
        ]);
        Sanctum::actingAs($user);

        $payload = ['mood_log_id' => $moodLog->id];
        $this->postJson('/api/v1/exercises/'.$exercise->id.'/complete', $payload)->assertOk();
        $this->postJson('/api/v1/exercises/'.$exercise->id.'/complete', $payload)->assertOk();

        $this->assertDatabaseCount('activity_completions', 1);
        $this->assertNotNull($moodLog->fresh()->suggestion_completed_at);

        $this->postJson('/api/v1/exercises/'.$exercise->id.'/complete')->assertOk();
        $this->postJson('/api/v1/exercises/'.$exercise->id.'/complete')->assertOk();

        $this->assertDatabaseCount('activity_completions', 3);
    }

    public function test_plan_activity_completion_is_idempotent(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::query()->create([
            'slug' => 'test-plan-exercise',
            'title' => 'تمرين الخطة',
            'description' => 'وصف التمرين',
            'type' => 'grounding',
            'duration_minutes' => 3,
            'content_steps' => [],
        ]);
        $plan = $user->wellnessPlans()->create([
            'title' => 'الخطة',
            'start_date' => '2026-10-03',
            'end_date' => '2026-10-09',
            'is_active' => true,
        ]);
        $day = $plan->days()->create([
            'day_number' => 1,
            'date' => '2026-10-03',
        ]);
        $planActivity = $day->activities()->create([
            'activity_type' => Exercise::class,
            'activity_id' => $exercise->id,
            'is_completed' => false,
        ]);
        Sanctum::actingAs($user);

        $payload = ['plan_activity_id' => $planActivity->id];
        $this->postJson('/api/v1/exercises/'.$exercise->id.'/complete', $payload)->assertOk();
        $this->postJson('/api/v1/exercises/'.$exercise->id.'/complete', $payload)->assertOk();

        $this->assertDatabaseCount('activity_completions', 1);
        $this->assertTrue($planActivity->fresh()->is_completed);
    }

    public function test_guest_and_blocked_user_cannot_view_progress(): void
    {
        $this->get('/api/v1/progress/insights')->assertUnauthorized();

        $blocked = User::factory()->create(['is_blocked' => true]);
        Sanctum::actingAs($blocked);

        $this->getJson('/api/v1/progress/insights')->assertForbidden();
    }

    private function moodLog(User $user, int $score, string $damascusTime): MoodLog
    {
        $log = $user->moodLogs()->create([
            'mood_score' => $score,
            'dismissed_suggestions' => [],
        ]);

        $log->forceFill([
            'created_at' => $this->utc($damascusTime),
            'updated_at' => $this->utc($damascusTime),
        ])->saveQuietly();

        return $log;
    }

    private function completion(User $user, string $type, string $damascusTime): void
    {
        $completion = ActivityCompletion::query()->create([
            'user_id' => $user->id,
            'activity_type' => $type,
            'activity_id' => 1,
            'source' => 'explore',
        ]);

        $completion->forceFill([
            'created_at' => $this->utc($damascusTime),
            'updated_at' => $this->utc($damascusTime),
        ])->saveQuietly();
    }

    private function journal(User $user, string $damascusTime): void
    {
        $journal = $user->journalEntries()->create([
            'body' => 'مدخلة تجريبية',
        ]);

        $journal->forceFill([
            'created_at' => $this->utc($damascusTime),
            'updated_at' => $this->utc($damascusTime),
        ])->saveQuietly();
    }

    private function utc(string $damascusTime): CarbonImmutable
    {
        return CarbonImmutable::parse($damascusTime, 'Asia/Damascus')->utc();
    }
}
