<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ArticleCommentReport;
use App\Models\Feedback;
use App\Models\SosCounselor;
use App\Models\User;
use App\Services\CommentModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSosModerationFeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_update_and_delete_sos_counselors(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $create = $this->post('/api/v1/admin/sos/counselors', [
            'name' => 'مستشار تجريبي',
            'specialty' => 'دعم نفسي',
            'phone' => '0944123456',
            'address' => 'دمشق',
            'city' => 'دمشق',
            'lat' => 33.5138,
            'lng' => 36.2765,
            'sort_order' => 3,
            'is_active' => true,
            'image' => $this->png('counselor.png'),
        ], ['Accept' => 'application/json']);

        $create
            ->assertCreated()
            ->assertJsonPath('counselor.phone', '+963944123456')
            ->assertJsonPath('counselor.is_active', true);

        $id = $create->json('counselor.id');
        $imagePath = SosCounselor::query()->findOrFail($id)->image_url;
        Storage::disk('public')->assertExists($imagePath);

        $this->postJson('/api/v1/admin/sos/counselors/'.$id, [
            'name' => 'اسم معدل',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('counselor.name', 'اسم معدل')
            ->assertJsonPath('counselor.is_active', false);

        $this->deleteJson('/api/v1/admin/sos/counselors/'.$id)->assertOk();
        $this->assertDatabaseMissing('sos_counselors', ['id' => $id]);
        Storage::disk('public')->assertMissing($imagePath);
    }

    public function test_admin_can_add_update_and_delete_hotlines_with_short_codes(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/v1/admin/sos/hotlines', [
            'slug' => 'local-emergency',
            'title' => 'طوارئ محلية',
            'phone' => '113',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $create->assertCreated()->assertJsonPath('hotline.phone', '113');
        $id = $create->json('hotline.id');

        $this->patchJson('/api/v1/admin/sos/hotlines/'.$id, ['phone' => '114'])
            ->assertOk()
            ->assertJsonPath('hotline.phone', '114');

        $this->deleteJson('/api/v1/admin/sos/hotlines/'.$id)->assertOk();
        $this->assertDatabaseMissing('sos_hotlines', ['id' => $id]);
    }

    public function test_reporting_marks_comment_for_review_and_admin_can_hide_and_restore_it(): void
    {
        $author = User::factory()->create(['display_name' => 'كاتب']);
        $reporter = User::factory()->create();
        $article = $this->article();
        $comment = $article->comments()->create([
            'user_id' => $author->id,
            'body' => 'تعليق بحاجة مراجعة',
            'status' => 'visible',
        ]);

        Sanctum::actingAs($reporter);
        $this->postJson('/api/v1/article-comments/'.$comment->id.'/report', [
            'reason' => 'محتوى مسيء',
        ])->assertCreated();

        $this->assertSame('needs_review', $comment->fresh()->moderation_status);

        $admin = $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/comment-reports')
            ->assertOk()
            ->assertJsonPath('data.0.id', $comment->id)
            ->assertJsonPath('data.0.reports.0.reason', 'محتوى مسيء');

        $this->patchJson('/api/v1/admin/comments/'.$comment->id.'/hide', [
            'admin_note' => 'تمت المراجعة يدوياً',
        ])
            ->assertOk()
            ->assertJsonPath('comment.status', 'hidden')
            ->assertJsonPath('comment.moderation.status', 'rejected');

        $report = ArticleCommentReport::query()->firstOrFail();
        $this->assertSame('resolved', $report->status);
        $this->assertSame($admin->id, $report->resolved_by);

        $this->patchJson('/api/v1/admin/comments/'.$comment->id.'/restore')
            ->assertOk()
            ->assertJsonPath('comment.status', 'visible')
            ->assertJsonPath('comment.moderation.status', 'approved');
    }

    public function test_backend_is_ready_to_store_future_ai_moderation_results(): void
    {
        $article = $this->article();
        $comment = $article->comments()->create([
            'user_id' => User::factory()->create()->id,
            'body' => 'نص تجريبي',
            'status' => 'visible',
        ]);

        app(CommentModerationService::class)->applyAiResult(
            $comment,
            'flagged',
            0.91,
            ['insult', 'harassment'],
        );

        $comment->refresh();
        $this->assertSame('flagged', $comment->moderation_status);
        $this->assertSame(0.91, $comment->moderation_score);
        $this->assertSame(['insult', 'harassment'], $comment->moderation_labels);
    }

    public function test_admin_can_list_decrypted_feedback_and_mark_it_reviewed(): void
    {
        $user = User::factory()->create(['display_name' => 'لونا']);
        $feedback = Feedback::query()->create([
            'user_id' => $user->id,
            'rating' => 4,
            'message' => 'اقتراح لتحسين التطبيق',
        ]);
        $admin = $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/feedback?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.message', 'اقتراح لتحسين التطبيق')
            ->assertJsonPath('data.0.user.display_name', 'لونا');

        $this->patchJson('/api/v1/admin/feedback/'.$feedback->id.'/review', [
            'admin_note' => 'تمت القراءة',
        ])
            ->assertOk()
            ->assertJsonPath('feedback.status', 'reviewed')
            ->assertJsonPath('feedback.admin_note', 'تمت القراءة');

        $feedback->refresh();
        $this->assertSame($admin->id, $feedback->reviewed_by);
        $this->assertNotNull($feedback->reviewed_at);
    }

    public function test_sos_open_is_counted_without_storing_user_identity(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/sos')->assertOk();

        $this->assertDatabaseHas('sos_daily_stats', [
            'date' => now('Asia/Damascus')->toDateString(),
            'opens_count' => 1,
        ]);
        $this->assertFalse(\Schema::hasColumn('sos_daily_stats', 'user_id'));
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function article(): Article
    {
        return Article::query()->create([
            'title' => 'مقال',
            'slug' => 'article-'.uniqid(),
            'summary' => 'ملخص',
            'content' => 'محتوى',
            'is_published' => true,
        ]);
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
