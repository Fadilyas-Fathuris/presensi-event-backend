<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_event_categories(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $createResponse = $this->postJson('/api/admin/event-categories', [
            'category_name' => 'Seminar',
            'description' => 'Kategori untuk acara seminar alumni',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Event category created successfully')
            ->assertJsonPath('data.category.category_name', 'Seminar');

        $categoryId = $createResponse->json('data.category.id');

        $this->getJson('/api/admin/event-categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.categories.0.id', $categoryId)
            ->assertJsonStructure([
                'data' => [
                    'categories' => [[
                        'id',
                        'category_name',
                        'description',
                        'created_at',
                        'updated_at',
                    ]],
                ],
            ]);

        $this->getJson("/api/admin/event-categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('data.category.category_name', 'Seminar');

        $this->putJson("/api/admin/event-categories/{$categoryId}", [
            'category_name' => 'Seminar Updated',
            'description' => 'Deskripsi diperbarui',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Event category updated successfully')
            ->assertJsonPath('data.category.category_name', 'Seminar Updated');

        $this->deleteJson("/api/admin/event-categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('message', 'Event category deleted successfully');

        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
    }

    public function test_category_used_by_an_event_cannot_be_deleted(): void
    {
        $admin = $this->createAdmin();
        $category = Category::query()->create([
            'category_name' => 'Reuni',
            'description' => 'Kategori reuni alumni',
        ]);

        Event::query()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'event_title' => 'Reuni Akbar',
            'location' => 'Aula',
            'event_date' => now()->addDay()->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'qr_token' => Str::uuid()->toString(),
            'status_event' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/event-categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Event category cannot be deleted because it is still used by events',
            ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
