<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticleFavoriteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_favorite_an_article_idempotently(): void
    {
        $user = User::factory()->create();
        $article = $this->article();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/articles/'.$article->id.'/favorite')
            ->assertOk()
            ->assertJsonPath('is_favorite', true)
            ->assertJsonPath('message', 'تم حفظ المقال في المفضلة.');

        $this->putJson('/api/v1/articles/'.$article->id.'/favorite')
            ->assertOk()
            ->assertJsonPath('is_favorite', true);

        $this->assertDatabaseCount('article_favorites', 1);
    }

    public function test_user_can_remove_an_article_from_favorites_idempotently(): void
    {
        $user = User::factory()->create();
        $article = $this->article();
        $user->favoriteArticles()->attach($article->id);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/articles/'.$article->id.'/favorite')
            ->assertOk()
            ->assertJsonPath('is_favorite', false)
            ->assertJsonPath('message', 'تمت إزالة المقال من المفضلة.');

        $this->deleteJson('/api/v1/articles/'.$article->id.'/favorite')
            ->assertOk()
            ->assertJsonPath('is_favorite', false);

        $this->assertDatabaseCount('article_favorites', 0);
    }

    public function test_favorites_list_contains_only_published_saved_articles(): void
    {
        $user = User::factory()->create();
        $published = $this->article();
        $unpublished = $this->article(false);
        $notSaved = $this->article();
        $user->favoriteArticles()->attach([$published->id, $unpublished->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/articles/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.is_favorite', true)
            ->assertJsonMissing(['id' => $unpublished->id])
            ->assertJsonMissing(['id' => $notSaved->id]);
    }

    public function test_article_list_and_show_include_the_favorite_state(): void
    {
        $user = User::factory()->create();
        $favorite = $this->article();
        $other = $this->article();
        $user->favoriteArticles()->attach($favorite->id);
        Sanctum::actingAs($user);

        $list = $this->getJson('/api/v1/articles')->assertOk();
        $items = collect($list->json('data'))->keyBy('id');

        $this->assertTrue($items[$favorite->id]['is_favorite']);
        $this->assertFalse($items[$other->id]['is_favorite']);

        $this->getJson('/api/v1/articles/'.$favorite->id)
            ->assertOk()
            ->assertJsonPath('article.is_favorite', true);
    }

    public function test_unpublished_article_cannot_be_favorited(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $article = $this->article(false);

        $this->putJson('/api/v1/articles/'.$article->id.'/favorite')
            ->assertNotFound();

        $this->assertDatabaseCount('article_favorites', 0);
    }

    private function article(bool $published = true): Article
    {
        return Article::query()->create([
            'title' => 'مقال تجريبي',
            'slug' => 'favorite-test-'.fake()->unique()->numerify('#####'),
            'summary' => 'ملخص المقال',
            'content' => 'محتوى المقال',
            'author_name' => 'فريق نفس',
            'is_published' => $published,
        ]);
    }
}
