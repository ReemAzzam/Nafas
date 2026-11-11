<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\SosCounselor;
use App\Models\SosHotline;
use App\Models\User;
use Database\Seeders\SosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SosApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_receives_json_401_without_accept_header(): void
    {
        $this->get('/api/v1/sos')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_blocked_user_cannot_access_sos(): void
    {
        $user = User::factory()->create(['is_blocked' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/sos')
            ->assertForbidden()
            ->assertJsonPath('message', 'تم حظر هذا الحساب لمخالفة الشروط.');
    }

    public function test_index_returns_active_catalogs_and_only_the_users_contacts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        SosHotline::query()->create([
            'slug' => 'police',
            'title' => 'الشرطة',
            'phone' => '112',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        SosHotline::query()->create([
            'slug' => 'hidden',
            'title' => 'مخفي',
            'phone' => '999',
            'sort_order' => 2,
            'is_active' => false,
        ]);
        SosCounselor::query()->create([
            'name' => 'مستشار تجريبي',
            'specialty' => 'دعم نفسي',
            'phone' => '+963944000001',
            'image_url' => 'sos-counselors/demo.png',
            'address' => 'دمشق',
            'city' => 'دمشق',
            'lat' => 33.504,
            'lng' => 36.276,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $contact = $user->sosContacts()->create([
            'name' => 'ماما',
            'phone' => '+963944123456',
        ]);
        $otherUser->sosContacts()->create([
            'name' => 'جهة أخرى',
            'phone' => '+963944654321',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/sos')
            ->assertOk()
            ->assertJsonCount(1, 'hotlines')
            ->assertJsonCount(1, 'counselors')
            ->assertJsonCount(1, 'contacts')
            ->assertJsonPath('hotlines.0.tel_uri', 'tel:112')
            ->assertJsonPath('counselors.0.maps_url', 'https://maps.google.com/?q=33.504,36.276')
            ->assertJsonPath('contacts.0.id', $contact->id)
            ->assertJsonPath('contacts.0.tel_uri', 'tel:+963944123456');
    }

    public function test_index_returns_sos_exercises_in_the_fixed_slug_order(): void
    {
        foreach ([
            'thought-dump',
            'calm-breathing',
            'grounding-5-4-3-2-1',
        ] as $slug) {
            Exercise::query()->create([
                'slug' => $slug,
                'title' => $slug,
                'description' => 'وصف قصير',
                'type' => 'breathing',
                'duration_minutes' => 2,
                'content_steps' => [],
            ]);
        }

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/sos')
            ->assertOk()
            ->assertJsonCount(3, 'calming_options')
            ->assertJsonPath('calming_options.0.slug', 'calm-breathing')
            ->assertJsonPath('calming_options.1.slug', 'grounding-5-4-3-2-1')
            ->assertJsonPath('calming_options.2.slug', 'thought-dump');
    }

    public function test_user_can_create_update_and_delete_a_contact(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/sos/contacts', [
            'name' => 'أختي',
            'phone' => '٠٩٤٤١٢٣٤٥٦',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('contact.phone', '+963944123456');

        $contactId = $create->json('contact.id');
        $storedPhone = DB::table('sos_contacts')->where('id', $contactId)->value('phone');

        $this->assertNotSame('+963944123456', $storedPhone);

        $this->putJson('/api/v1/sos/contacts/'.$contactId, [
            'name' => 'أختي الكبرى',
            'phone' => '+963944654321',
        ])
            ->assertOk()
            ->assertJsonPath('contact.name', 'أختي الكبرى')
            ->assertJsonPath('contact.phone', '+963944654321');

        $this->deleteJson('/api/v1/sos/contacts/'.$contactId)
            ->assertOk()
            ->assertJsonPath('message', 'تم حذف جهة الاتصال.');

        $this->assertDatabaseMissing('sos_contacts', ['id' => $contactId]);
    }

    public function test_contact_phone_must_be_a_syrian_mobile_number(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/sos/contacts', [
            'name' => 'المنزل',
            'phone' => '0112222222',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone')
            ->assertJsonPath('errors.phone.0', 'يجب أن يكون الرقم موبايل سوري صالح.');
    }

    public function test_user_cannot_manage_another_users_contact(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $contact = $owner->sosContacts()->create([
            'name' => 'قريب',
            'phone' => '+963944123456',
        ]);

        Sanctum::actingAs($otherUser);

        $this->putJson('/api/v1/sos/contacts/'.$contact->id, [
            'name' => 'اسم جديد',
            'phone' => '+963944654321',
        ])->assertNotFound();

        $this->deleteJson('/api/v1/sos/contacts/'.$contact->id)
            ->assertNotFound();

        $this->assertDatabaseHas('sos_contacts', ['id' => $contact->id]);
    }

    public function test_user_cannot_have_more_than_five_contacts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $index) {
            $user->sosContacts()->create([
                'name' => 'جهة '.$index,
                'phone' => '+96394412345'.$index,
            ]);
        }

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sos/contacts', [
            'name' => 'جهة سادسة',
            'phone' => '+963944123459',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'يمكنك إضافة خمسة أشخاص كحد أقصى.');
    }

    public function test_sos_seeder_stores_counselor_images_on_the_public_disk(): void
    {
        Storage::fake('public');

        $this->seed(SosSeeder::class);

        $this->assertDatabaseHas('sos_hotlines', [
            'slug' => 'red-crescent-jaramana-1',
            'phone' => '0952133133',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('sos_hotlines', [
            'slug' => 'red-crescent-jaramana-2',
            'phone' => '0941133133',
            'is_active' => true,
        ]);

        Storage::disk('public')->assertExists('sos-counselors/sarah-hassan.jpg');
        Storage::disk('public')->assertExists('sos-counselors/malik-ali.jpg');

        $this->assertDatabaseHas('sos_counselors', [
            'phone' => '+963944000001',
            'image_url' => 'sos-counselors/sarah-hassan.jpg',
        ]);
        $this->assertDatabaseHas('sos_counselors', [
            'phone' => '+963944000002',
            'image_url' => 'sos-counselors/malik-ali.jpg',
        ]);
    }
}
