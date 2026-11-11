<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_an_encrypted_journal_entry(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/journals', [
            'prompt' => 'ما الذي تحتاجه الآن؟',
            'body' => "  أحتاج إلى بعض الهدوء.\nوسأمنح نفسي وقتًا.  ",
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'حُفظت كلماتك.')
            ->assertJsonPath('journal_entry.prompt', 'ما الذي تحتاجه الآن؟')
            ->assertJsonPath('journal_entry.body', "أحتاج إلى بعض الهدوء.\nوسأمنح نفسي وقتًا.")
            ->assertJsonPath('journal_entry.word_count', 7);

        $id = $response->json('journal_entry.id');
        $stored = DB::table('journal_entries')->where('id', $id)->first();

        $this->assertNotSame('ما الذي تحتاجه الآن؟', $stored->prompt);
        $this->assertNotSame("أحتاج إلى بعض الهدوء.\nوسأمنح نفسي وقتًا.", $stored->body);
    }

    public function test_list_contains_only_the_users_entries_and_uses_summaries(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $older = $user->journalEntries()->create([
            'prompt' => null,
            'body' => str_repeat('كلمة ', 30),
        ]);
        $newer = $user->journalEntries()->create([
            'prompt' => 'سؤال اليوم',
            'body' => 'هذه أحدث مدخلة',
        ]);
        $otherUser->journalEntries()->create([
            'prompt' => null,
            'body' => 'هذه مدخلة مستخدم آخر',
        ]);

        $older->update(['created_at' => now()->subDay()]);
        $newer->update(['created_at' => now()]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/journals')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.prompt', 'سؤال اليوم')
            ->assertJsonPath('data.0.excerpt', 'هذه أحدث مدخلة')
            ->assertJsonMissingPath('data.0.body')
            ->assertJsonMissing(['body' => 'هذه مدخلة مستخدم آخر']);
    }

    public function test_user_can_view_only_their_own_entry(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $entry = $owner->journalEntries()->create([
            'prompt' => null,
            'body' => 'مساحة خاصة',
        ]);

        Sanctum::actingAs($otherUser);
        $this->getJson('/api/v1/journals/'.$entry->id)->assertNotFound();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/journals/'.$entry->id)
            ->assertOk()
            ->assertJsonPath('journal_entry.body', 'مساحة خاصة');
    }

    public function test_user_can_delete_only_their_own_entry(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $entry = $owner->journalEntries()->create([
            'prompt' => null,
            'body' => 'سأحذف هذه المدخلة',
        ]);

        Sanctum::actingAs($otherUser);
        $this->deleteJson('/api/v1/journals/'.$entry->id)->assertNotFound();

        Sanctum::actingAs($owner);
        $this->deleteJson('/api/v1/journals/'.$entry->id)
            ->assertOk()
            ->assertJsonPath('message', 'تم حذف المدخلة من يومياتك.');

        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
    }

    public function test_empty_entry_returns_arabic_validation_error(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/journals', ['body' => ' '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body')
            ->assertJsonPath('errors.body.0', 'اكتب ما يدور في بالك قبل الحفظ.');
    }

    public function test_entries_are_paginated_twenty_per_page(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 21) as $index) {
            $user->journalEntries()->create([
                'body' => 'مدخلة رقم '.$index,
            ]);
        }

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/journals')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.per_page', 20);
    }
}
