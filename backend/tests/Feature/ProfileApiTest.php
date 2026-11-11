<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Notifications\SendOTPNotification;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_profile_and_the_image_is_optional(): void
    {
        $user = User::factory()->create([
            'display_name' => 'لينا',
            'profile_image_url' => null,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.display_name', 'لينا')
            ->assertJsonPath('user.profile_image_url', null);
    }

    public function test_user_can_update_profile_information(): void
    {
        $user = User::factory()->create([
            'is_anonymous' => false,
            'country_code' => 'SY',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/profile', [
            'name' => 'لينا أحمد',
            'display_name' => 'لونا',
            'phone' => '٠٩٤٤١٢٣٤٥٦',
            'country_code' => 'sy',
            'date_of_birth' => '2000-05-10',
            'status' => 'worker',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'لينا أحمد')
            ->assertJsonPath('user.display_name', 'لونا')
            ->assertJsonPath('user.formatted_phone', '+963944123456')
            ->assertJsonPath('user.country_code', 'SY')
            ->assertJsonPath('user.status', 'worker');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'display_name' => 'لونا',
            'phone' => '+963944123456',
        ]);
    }

    public function test_profile_update_has_arabic_validation_errors(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_anonymous' => false]));

        $this->patchJson('/api/v1/profile', [
            'display_name' => '',
            'date_of_birth' => now()->subYears(17)->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.display_name.0', 'الاسم الظاهر مطلوب.')
            ->assertJsonPath('errors.date_of_birth.0', 'يجب ألا يقل العمر عن 18 سنة.');
    }

    public function test_user_can_upload_replace_and_delete_profile_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $first = $this->post('/api/v1/profile/image', [
            'image' => $this->png('first.png'),
        ], ['Accept' => 'application/json']);

        $first->assertOk();
        $firstPath = $user->fresh()->profile_image_url;
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->post('/api/v1/profile/image', [
            'image' => $this->png('second.png'),
        ], ['Accept' => 'application/json']);

        $second->assertOk();
        $secondPath = $user->fresh()->profile_image_url;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);

        $this->deleteJson('/api/v1/profile/image')
            ->assertOk()
            ->assertJsonPath('user.profile_image_url', null);

        Storage::disk('public')->assertMissing($secondPath);
    }

    public function test_profile_image_rejects_svg_and_files_larger_than_two_megabytes(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $this->post('/api/v1/profile/image', [
            'image' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->post('/api/v1/profile/image', [
            'image' => UploadedFile::fake()->create('avatar.jpg', 2049, 'image/jpeg'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_user_can_change_email_after_verifying_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);
        $currentToken = $user->createToken('current')->plainTextToken;
        $user->createToken('other');
        $otp = null;

        $this->withToken($currentToken)
            ->postJson('/api/v1/profile/email/request', [
                'email' => 'new@example.com',
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJsonPath('expires_in', 300);

        Notification::assertSentOnDemand(
            SendOTPNotification::class,
            function (SendOTPNotification $notification) use (&$otp) {
                $otp = $notification->otp;

                return $notification->purpose === OtpService::EMAIL_CHANGE;
            }
        );

        $this->assertSame(
            'new@example.com',
            Cache::get('profile_email_change_user_'.$user->id)
        );

        $this->withToken($currentToken)
            ->postJson('/api/v1/profile/email/verify', [
                'email' => 'new@example.com',
                'otp' => $otp,
            ])
            ->assertOk()
            ->assertJsonPath('user.email', 'new@example.com');

        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_email_change_rejects_wrong_otp_and_an_email_used_by_active_account(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        User::factory()->create(['email' => 'used@example.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/email/request', [
            'email' => 'used@example.com',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'البريد الإلكتروني مستخدم في حساب نشط آخر.');

        Cache::put('profile_email_change_user_'.$user->id, 'new@example.com', now()->addMinutes(5));
        Cache::put(
            'otp_email_change_new@example.com',
            hash('sha256', '123456'),
            now()->addMinutes(5)
        );
        Cache::put('otp_attempts_email_change_new@example.com', 0, now()->addMinutes(5));

        $this->postJson('/api/v1/profile/email/verify', [
            'email' => 'new@example.com',
            'otp' => '999999',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.otp.0', 'رمز التحقق غير صحيح أو انتهت صلاحيته.');
    }

    public function test_deleting_account_soft_deletes_user_revokes_sessions_and_masks_comments(): void
    {
        $user = User::factory()->create([
            'email' => 'deleted@example.com',
            'display_name' => 'الاسم القديم',
        ]);
        $article = Article::query()->create([
            'title' => 'مقال',
            'slug' => 'profile-delete-test',
            'summary' => 'ملخص',
            'content' => 'محتوى',
            'is_published' => true,
        ]);
        $comment = $article->comments()->create([
            'user_id' => $user->id,
            'body' => 'تعليق محفوظ',
            'status' => 'visible',
        ]);
        $user->journalEntries()->create(['body' => 'يومية محفوظة']);
        $token = $user->createToken('current')->plainTextToken;
        $user->createToken('other');

        $this->withToken($token)
            ->deleteJson('/api/v1/profile', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('message', 'تم حذف الحساب بنجاح.');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'deleted@example.com',
            'display_name' => 'الاسم القديم',
        ]);
        $this->assertDatabaseHas('article_comments', ['id' => $comment->id]);
        $this->assertDatabaseHas('journal_entries', ['user_id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/articles/'.$article->id.'/comments')
            ->assertOk()
            ->assertJsonPath('data.0.author.display_name', 'حساب محذوف');
    }

    public function test_deleted_email_can_register_a_completely_new_account(): void
    {
        Notification::fake();
        $oldUser = User::factory()->create(['email' => 'again@example.com']);
        $oldUser->delete();

        $this->postJson('/api/v1/auth/register', [
            'is_anonymous' => false,
            'name' => 'مستخدم جديد',
            'display_name' => 'الجديد',
            'email' => 'again@example.com',
            'password' => 'Secret1@',
            'password_confirmation' => 'Secret1@',
            'status' => 'student',
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'again@example.com')
            ->assertJsonPath('user.profile_image_url', null);

        $newUser = User::query()->where('email', 'again@example.com')->firstOrFail();

        $this->assertNotSame($oldUser->id, $newUser->id);
        $this->assertSame(2, User::withTrashed()->where('email', 'again@example.com')->count());
        $this->assertSame('pending', $newUser->onboarding_status);
    }

    public function test_wrong_password_does_not_delete_the_account(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/profile', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'كلمة المرور الحالية غير صحيحة.');

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_guest_and_blocked_user_cannot_access_profile(): void
    {
        $this->get('/api/v1/profile')->assertUnauthorized();

        $blocked = User::factory()->create(['is_blocked' => true]);
        Sanctum::actingAs($blocked);

        $this->getJson('/api/v1/profile')->assertForbidden();
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
            )
        );
    }
}
