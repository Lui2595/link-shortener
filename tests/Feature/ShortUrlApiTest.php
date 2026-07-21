<?php

namespace Tests\Feature;

use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortUrlApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_urls(): void
    {
        $this->getJson('/api/urls')->assertUnauthorized();
    }

    public function test_user_can_create_list_update_and_delete_urls(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)->postJson('/api/urls', [
            'original_url' => 'https://spot2.mx/spots/offices/long-path-example',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.original_url', 'https://spot2.mx/spots/offices/long-path-example');

        $code = $create->json('data.code');
        $id = $create->json('data.id');

        $this->assertSame(8, strlen($code));
        $this->assertTrue(strlen($code) < strlen('spots/offices/long-path-example'));

        $this->actingAs($user)
            ->getJson('/api/urls')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)
            ->putJson("/api/urls/{$id}", [
                'original_url' => 'https://spot2.mx/updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.original_url', 'https://spot2.mx/updated');

        $this->actingAs($user)
            ->deleteJson("/api/urls/{$id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('short_urls', ['id' => $id]);
    }

    public function test_user_cannot_manage_another_users_url(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $url = ShortUrl::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->getJson("/api/urls/{$url->id}")
            ->assertNotFound();

        $this->actingAs($intruder)
            ->putJson("/api/urls/{$url->id}", [
                'original_url' => 'https://evil.example',
            ])
            ->assertNotFound();

        $this->actingAs($intruder)
            ->deleteJson("/api/urls/{$url->id}")
            ->assertNotFound();
    }

    public function test_it_rejects_non_http_urls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/urls', [
                'original_url' => 'ftp://example.com/file',
            ])
            ->assertStatus(422);
    }
}
