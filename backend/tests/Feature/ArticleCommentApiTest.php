<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticleCommentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_comments_list_uses_only_display_name_and_hides_hidden_comments(): void
    {
        $viewer = User::factory()->create(['display_name' => 'لونا']);
        $anonymous = User::factory()->create([
            'name' => 'مستخدم مجهول',
            'display_name' => 'نسمة هادئة',
            'is_anonymous' => true,
        ]);
        $article = $this->article();

        $visible = $article->comments()->create([
            'user_id' => $anonymous->id,
            'body' => 'تعليق ظاهر',
            'status' => ArticleComment::STATUS_VISIBLE,
        ]);
        $article->comments()->create([
            'user_id' => $anonymous->id,
            'body' => 'تعليق مخفي',
            'status' => ArticleComment::STATUS_HIDDEN,
        ]);
        $visible->likes()->attach($viewer->id);

        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/articles/'.$article->id.'/comments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.author.display_name', 'نسمة هادئة')
            ->assertJsonPath('data.0.likes_count', 1)
            ->assertJsonPath('data.0.is_liked', true)
            ->assertJsonPath('data.0.is_mine', false)
            ->assertJsonMissing(['name' => 'مستخدم مجهول'])
            ->assertJsonMissing(['email' => $anonymous->email])
            ->assertJsonMissing(['body' => 'تعليق مخفي']);
    }

    public function test_user_can_publish_a_normalized_plain_text_comment(): void
    {
        $user = User::factory()->create(['display_name' => 'نور']);
        $article = $this->article();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/articles/'.$article->id.'/comments', [
            'body' => '  فكرة   <b>جميلة</b>  ',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'تم نشر تعليقك.')
            ->assertJsonPath('comment.body', 'فكرة جميلة')
            ->assertJsonPath('comment.author.display_name', 'نور')
            ->assertJsonPath('comment.is_mine', true)
            ->assertJsonPath('comment.is_liked', false)
            ->assertJsonPath('comment.likes_count', 0);

        $this->assertDatabaseHas('article_comments', [
            'article_id' => $article->id,
            'user_id' => $user->id,
            'body' => 'فكرة جميلة',
            'status' => ArticleComment::STATUS_VISIBLE,
        ]);
    }

    public function test_comment_body_has_arabic_validation_errors(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $article = $this->article();

        $this->postJson('/api/v1/articles/'.$article->id.'/comments', [
            'body' => ' ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body')
            ->assertJsonPath('errors.body.0', 'يرجى كتابة التعليق.');
    }

    public function test_comments_of_an_unpublished_article_return_404(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $article = $this->article(false);

        $this->getJson('/api/v1/articles/'.$article->id.'/comments')->assertNotFound();
        $this->postJson('/api/v1/articles/'.$article->id.'/comments', [
            'body' => 'لن ينشر',
        ])->assertNotFound();
    }

    public function test_user_can_delete_only_their_own_comment(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $article = $this->article();
        $comment = $article->comments()->create([
            'user_id' => $owner->id,
            'body' => 'تعليقي',
        ]);

        Sanctum::actingAs($otherUser);
        $this->deleteJson('/api/v1/articles/'.$article->id.'/comments/'.$comment->id)
            ->assertNotFound();

        Sanctum::actingAs($owner);
        $this->deleteJson('/api/v1/articles/'.$article->id.'/comments/'.$comment->id)
            ->assertOk()
            ->assertJsonPath('message', 'تم حذف تعليقك.');

        $this->assertDatabaseMissing('article_comments', ['id' => $comment->id]);
    }

    public function test_like_and_unlike_are_idempotent(): void
    {
        $user = User::factory()->create();
        $comment = $this->comment();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/article-comments/'.$comment->id.'/like')
            ->assertOk()
            ->assertJsonPath('is_liked', true)
            ->assertJsonPath('likes_count', 1);

        $this->postJson('/api/v1/article-comments/'.$comment->id.'/like')
            ->assertOk()
            ->assertJsonPath('likes_count', 1);

        $this->deleteJson('/api/v1/article-comments/'.$comment->id.'/like')
            ->assertOk()
            ->assertJsonPath('is_liked', false)
            ->assertJsonPath('likes_count', 0);
    }

    public function test_report_is_created_only_once_per_user(): void
    {
        $user = User::factory()->create();
        $comment = $this->comment();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/article-comments/'.$comment->id.'/report', [
            'reason' => 'تعليق مؤذٍ',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'تم إرسال البلاغ للمراجعة.');

        $this->postJson('/api/v1/article-comments/'.$comment->id.'/report', [
            'reason' => 'سبب آخر',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'سبق أن أرسلت بلاغًا عن هذا التعليق.');

        $this->assertDatabaseCount('article_comment_reports', 1);
        $this->assertDatabaseHas('article_comment_reports', [
            'article_comment_id' => $comment->id,
            'user_id' => $user->id,
            'reason' => 'تعليق مؤذٍ',
        ]);
    }

    public function test_comments_are_paginated_twenty_per_page(): void
    {
        $user = User::factory()->create();
        $article = $this->article();

        foreach (range(1, 21) as $index) {
            $article->comments()->create([
                'user_id' => $user->id,
                'body' => 'تعليق '.$index,
            ]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/articles/'.$article->id.'/comments')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.per_page', 20);
    }

    private function article(bool $published = true): Article
    {
        return Article::query()->create([
            'title' => 'مقال تجريبي',
            'slug' => 'test-article-'.fake()->unique()->numerify('#####'),
            'summary' => 'ملخص المقال',
            'content' => 'محتوى المقال',
            'author_name' => 'فريق نفس',
            'is_published' => $published,
        ]);
    }

    private function comment(): ArticleComment
    {
        return $this->article()->comments()->create([
            'user_id' => User::factory()->create()->id,
            'body' => 'تعليق تجريبي',
            'status' => ArticleComment::STATUS_VISIBLE,
        ]);
    }
}
