<?php

namespace Tests\Feature\Admin;

use App\Models\MoodLog;
use App\Models\SosDailyStat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\NafasDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_can_access_admin_routes(): void
    {
        $this->get('/api/v1/admin/dashboard')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('message', 'غير مصرح لك بالدخول إلى لوحة الإدارة.');

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_blocked' => true]));
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_dashboard_returns_business_metrics_without_private_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        User::factory()->create(['role' => 'user', 'is_blocked' => true]);
        $deleted = User::factory()->create(['role' => 'user']);
        $deleted->delete();
        MoodLog::query()->create([
            'user_id' => $user->id,
            'mood_score' => 4,
            'dismissed_suggestions' => [],
        ]);
        SosDailyStat::query()->create([
            'date' => now('Asia/Damascus')->toDateString(),
            'opens_count' => 3,
        ]);
        $user->journalEntries()->create(['body' => 'نص يومية خاص جداً']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.users.active', 1)
            ->assertJsonPath('data.users.blocked', 1)
            ->assertJsonPath('data.users.deleted', 1)
            ->assertJsonPath('data.engagement.mood_logs_total', 1)
            ->assertJsonPath('data.engagement.sos_opens_last_7_days', 3)
            ->assertJsonCount(7, 'data.weekly_activity')
            ->assertDontSee('نص يومية خاص جداً');
    }

    public function test_admin_can_filter_view_block_and_unblock_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'display_name' => 'مستخدم للبحث',
            'role' => 'user',
        ]);
        $user->journalEntries()->create(['body' => 'محتوى لا يظهر للأدمن']);
        $token = $user->createToken('mobile');
        $deleted = User::factory()->create(['role' => 'user']);
        $deleted->delete();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/users?search=مستخدم للبحث&status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user->id);

        $this->getJson('/api/v1/admin/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('user.counts.journal_entries', 1)
            ->assertDontSee('محتوى لا يظهر للأدمن');

        $this->patchJson('/api/v1/admin/users/'.$user->id.'/block')
            ->assertOk()
            ->assertJsonPath('user.is_blocked', true);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);

        $this->patchJson('/api/v1/admin/users/'.$user->id.'/unblock')
            ->assertOk()
            ->assertJsonPath('user.is_blocked', false);

        $this->getJson('/api/v1/admin/users?status=deleted')
            ->assertOk()
            ->assertJsonPath('data.0.id', $deleted->id)
            ->assertJsonPath('data.0.deleted_at', fn ($value) => $value !== null);
    }

    public function test_dashboard_counts_first_week_retention_only_for_seven_consecutive_mood_days(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-04 12:00:00', 'Asia/Damascus'));

        $admin = User::factory()->create(['role' => 'admin']);
        $retained = User::factory()->create([
            'role' => 'user',
            'created_at' => CarbonImmutable::parse('2026-09-20 10:00:00', 'Asia/Damascus')->utc(),
        ]);
        $missedDay = User::factory()->create([
            'role' => 'user',
            'created_at' => CarbonImmutable::parse('2026-09-20 11:00:00', 'Asia/Damascus')->utc(),
        ]);
        User::factory()->create([
            'role' => 'user',
            'created_at' => CarbonImmutable::parse('2026-10-01 11:00:00', 'Asia/Damascus')->utc(),
        ]);

        foreach (range(0, 6) as $day) {
            $log = MoodLog::query()->create([
                'user_id' => $retained->id,
                'mood_score' => 4,
                'dismissed_suggestions' => [],
            ]);
            $log->forceFill([
                'created_at' => CarbonImmutable::parse('2026-09-20 18:00:00', 'Asia/Damascus')->addDays($day)->utc(),
            ])->saveQuietly();
        }

        foreach ([0, 1, 2, 4, 5, 6] as $day) {
            $log = MoodLog::query()->create([
                'user_id' => $missedDay->id,
                'mood_score' => 3,
                'dismissed_suggestions' => [],
            ]);
            $log->forceFill([
                'created_at' => CarbonImmutable::parse('2026-09-20 19:00:00', 'Asia/Damascus')->addDays($day)->utc(),
            ])->saveQuietly();
        }

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.engagement.first_7_days_retention.rate_percent', 50)
            ->assertJsonPath('data.engagement.first_7_days_retention.retained_users', 1)
            ->assertJsonPath('data.engagement.first_7_days_retention.eligible_users', 2)
            ->assertJsonPath('data.engagement.first_7_days_retention.required_consecutive_days', 7);

        CarbonImmutable::setTestNow();
    }

    public function test_admin_cannot_block_an_admin_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/v1/admin/users/'.$admin->id.'/block')
            ->assertUnprocessable()
            ->assertJsonPath('errors.user.0', 'لا يمكن تعديل حالة حساب إداري من هذا المسار.');
    }

    public function test_demo_seeder_creates_verified_admin_account(): void
    {
        $this->seed(NafasDemoSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@nafas.test',
            'role' => 'admin',
        ]);
        $this->assertNotNull(User::query()->where('email', 'admin@nafas.test')->first()->email_verified_at);
    }
}
