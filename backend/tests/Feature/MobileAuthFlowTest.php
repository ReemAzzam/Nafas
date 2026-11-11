<?php
namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SendOTPNotification;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobileAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_verify_skip_and_home_work_with_real_bearer_token(): void
    {
        Notification::fake();
        $email = 'mobile@example.com';
        $this->postJson('/api/v1/auth/register', [
            'is_anonymous' => true, 'display_name' => 'نسمة',
            'email' => $email, 'password' => 'Password1!',
            'password_confirmation' => 'Password1!', 'status' => 'student',
        ])->assertCreated();
        $user = User::where('email', $email)->firstOrFail();
        $otp = null;
        Notification::assertSentTo($user, SendOTPNotification::class, function ($notification) use (&$otp) {
            $otp = $notification->otp;
            return true;
        });
        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'Password1!'])
            ->assertForbidden()->assertJsonPath('requires_otp', true);
        $this->postJson('/api/v1/auth/verify-otp', ['email' => $email, 'otp' => '000000'])
            ->assertStatus(400);
        $response = $this->postJson('/api/v1/auth/verify-otp', ['email' => $email, 'otp' => $otp]);
        $response->assertOk()->assertJsonPath('user.email', $email);
        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
        $this->withToken($token)->postJson('/api/v1/onboarding/skip')->assertOk();
        $this->withToken($token)->getJson('/api/v1/home')->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'Password1!'])
            ->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_resend_request_autoloads_on_linux_and_obeys_cooldown(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->postJson('/api/v1/auth/resend-otp', ['email' => $user->email])->assertOk();
        $this->postJson('/api/v1/auth/resend-otp', ['email' => $user->email])->assertStatus(429);
        $this->assertGreaterThan(0, app(OtpService::class)->retryAfter($user->email, OtpService::REGISTER));
    }

    public function test_delivery_failure_rolls_back_new_account_and_clears_otp(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));
        $this->postJson('/api/v1/auth/register', [
            'is_anonymous' => true, 'display_name' => 'نسمة',
            'email' => 'failed@example.com', 'password' => 'Password1!',
            'password_confirmation' => 'Password1!', 'status' => 'student',
        ])->assertStatus(500);
        $this->assertDatabaseMissing('users', ['email' => 'failed@example.com']);
        $this->assertSame(0, app(OtpService::class)->retryAfter('failed@example.com', OtpService::REGISTER));
    }
    public function test_local_demo_account_can_login_and_open_home_directly(): void
    {
        $this->seed(\Database\Seeders\MobileLocalSeeder::class);
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'lina@nafas.test', 'password' => 'Secret1@',
        ])->assertOk()->assertJsonPath('user.onboarding_status', 'skipped');
        $this->withToken($response->json('token'))->getJson('/api/v1/home')->assertOk();
    }
}
