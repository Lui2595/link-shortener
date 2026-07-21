<?php

namespace Tests\Feature;

use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_code_renders_redirect_page(): void
    {
        $url = ShortUrl::factory()->create([
            'code' => 'abcd2345',
            'original_url' => 'https://spot2.mx/destination',
        ]);

        $this->get('/abcd2345')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('redirect')
                ->where('originalUrl', 'https://spot2.mx/destination')
                ->where('code', 'abcd2345')
            );

        $this->assertSame(1, $url->fresh()->clicks);
    }

    public function test_short_code_returns_json_when_requested(): void
    {
        ShortUrl::factory()->create([
            'code' => 'jsoncode',
            'original_url' => 'https://spot2.mx/json',
        ]);

        $this->getJson('/jsoncode')
            ->assertOk()
            ->assertJson([
                'original_url' => 'https://spot2.mx/json',
                'code' => 'jsoncode',
            ]);
    }

    public function test_direct_query_returns_http_redirect(): void
    {
        ShortUrl::factory()->create([
            'code' => 'redirect',
            'original_url' => 'https://spot2.mx/away',
        ]);

        $this->get('/redirect?direct=1')
            ->assertRedirect('https://spot2.mx/away');
    }

    public function test_unknown_code_returns_404(): void
    {
        $this->get('/unknown1')->assertNotFound();
    }

    public function test_home_page_is_reachable(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_urls_panel_requires_auth(): void
    {
        $this->get('/urls')->assertRedirect('/');
    }

    public function test_authenticated_user_can_view_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/urls')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('urls/index'));
    }
}
