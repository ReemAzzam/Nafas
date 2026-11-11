<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Exercise;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_publish_and_soft_delete_article(): void
    {
        $this->actingAsAdmin();
        $goal = Goal::query()->create(['title' => 'هدف', 'icon' => 'icon']);

        $create = $this->postJson('/api/v1/admin/articles', [
            'title' => 'مقال جديد',
            'slug' => 'new-article',
            'summary' => 'ملخص المقال',
            'content' => 'محتوى المقال الكامل',
            'author_name' => 'فريق نفس',
            'is_published' => false,
            'goal_ids' => [$goal->id],
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('article.slug', 'new-article')
            ->assertJsonPath('article.is_published', false)
            ->assertJsonPath('article.goals.0.id', $goal->id);

        $articleId = $create->json('article.id');

        $this->postJson('/api/v1/admin/articles/'.$articleId, [
            'title' => 'عنوان معدل',
        ])->assertOk()->assertJsonPath('article.title', 'عنوان معدل');

        $this->patchJson('/api/v1/admin/articles/'.$articleId.'/publish')
            ->assertOk()
            ->assertJsonPath('article.is_published', true);

        $this->deleteJson('/api/v1/admin/articles/'.$articleId)->assertOk();
        $this->assertSoftDeleted('articles', ['id' => $articleId]);

        $this->getJson('/api/v1/admin/articles?status=deleted')
            ->assertOk()
            ->assertJsonPath('data.0.id', $articleId);
    }

    public function test_admin_can_manage_exercise_and_unpublished_exercise_is_hidden_from_users(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/v1/admin/exercises', [
            'slug' => 'admin-created-exercise',
            'title' => 'تمرين جديد',
            'description' => 'وصف التمرين',
            'type' => 'breathing',
            'duration_minutes' => 3,
            'content_steps' => [
                ['title' => 'ابدأ', 'body' => 'خذ نفساً هادئاً.'],
            ],
            'media_type' => 'video',
            'media_url' => 'https://www.youtube.com/shorts/CdnAL_gtIB0',
            'media_credit' => 'ملاك فاعور',
            'is_published' => true,
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('exercise.is_published', true)
            ->assertJsonPath('exercise.media_type', 'video')
            ->assertJsonPath('exercise.media_credit', 'ملاك فاعور');
        $exerciseId = $create->json('exercise.id');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/exercises/'.$exerciseId)
            ->assertOk()
            ->assertJsonPath('exercise.media.type', 'video')
            ->assertJsonPath('exercise.media.url', 'https://www.youtube.com/shorts/CdnAL_gtIB0')
            ->assertJsonPath('exercise.media.credit', 'ملاك فاعور');

        $this->actingAsAdmin();

        $this->patchJson('/api/v1/admin/exercises/'.$exerciseId.'/unpublish')
            ->assertOk()
            ->assertJsonPath('exercise.is_published', false);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/exercises/'.$exerciseId)->assertNotFound();
    }

    public function test_sos_exercise_cannot_be_deleted_but_regular_exercise_can(): void
    {
        $this->actingAsAdmin();
        $sos = $this->exercise('calm-breathing');
        $regular = $this->exercise('regular-exercise');

        $this->deleteJson('/api/v1/admin/exercises/'.$sos->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.exercise.0', 'لا يمكن حذف تمرين مرتبط بوضع SOS. يمكن تعديله فقط.');

        $this->deleteJson('/api/v1/admin/exercises/'.$regular->id)->assertOk();
        $this->assertSoftDeleted('exercises', ['id' => $regular->id]);
    }

    public function test_unpublished_article_is_not_available_to_users(): void
    {
        $article = Article::query()->create([
            'title' => 'مسودة',
            'slug' => 'draft-test',
            'summary' => 'ملخص',
            'content' => 'محتوى',
            'is_published' => false,
        ]);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/articles/'.$article->id)->assertNotFound();
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function exercise(string $slug): Exercise
    {
        return Exercise::query()->create([
            'slug' => $slug,
            'title' => $slug,
            'description' => 'وصف',
            'type' => 'breathing',
            'duration_minutes' => 2,
            'content_steps' => [['title' => 'خطوة', 'body' => 'تفصيل']],
            'is_published' => true,
        ]);
    }
}
