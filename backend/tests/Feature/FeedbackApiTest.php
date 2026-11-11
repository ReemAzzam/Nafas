<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_feedback_with_an_encrypted_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/feedback', [
            'rating' => 5,
            'message' => '  تجربة <b>مريحة</b> جدًا.  ',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'شكرًا لمشاركتنا رأيك.')
            ->assertJsonPath('feedback.rating', 5)
            ->assertJsonPath('feedback.message', 'تجربة مريحة جدًا.');

        $id = $response->json('feedback.id');
        $stored = DB::table('feedbacks')->where('id', $id)->first();

        $this->assertSame($user->id, $stored->user_id);
        $this->assertNotSame('تجربة مريحة جدًا.', $stored->message);
    }

    public function test_feedback_message_is_optional(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/feedback', [
            'rating' => 4,
        ])
            ->assertCreated()
            ->assertJsonPath('feedback.rating', 4)
            ->assertJsonPath('feedback.message', null);
    }

    public function test_rating_is_required_and_must_be_between_one_and_five(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/feedback', [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.rating.0',
                'يرجى اختيار تقييم من نجمة إلى خمس نجوم.'
            );

        $this->postJson('/api/v1/feedback', ['rating' => 6])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.rating.0',
                'يجب أن يكون التقييم بين نجمة وخمس نجوم.'
            );
    }

    public function test_guest_cannot_submit_feedback(): void
    {
        $this->post('/api/v1/feedback', [
            'rating' => 5,
        ])->assertUnauthorized();
    }

    public function test_blocked_user_cannot_submit_feedback(): void
    {
        $user = User::factory()->create(['is_blocked' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/feedback', [
            'rating' => 5,
        ])->assertForbidden();
    }
}
