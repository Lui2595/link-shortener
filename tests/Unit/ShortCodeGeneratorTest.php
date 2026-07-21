<?php

namespace Tests\Unit;

use App\Models\ShortUrl;
use App\Services\ShortCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_eight_character_readable_codes(): void
    {
        $generator = new ShortCodeGenerator;
        $code = $generator->generate();

        $this->assertSame(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[abcdefghjkmnpqrstuvwxyz23456789]{8}$/', $code);
    }

    public function test_it_avoids_colliding_with_existing_codes(): void
    {
        ShortUrl::factory()->create(['code' => 'abcdefgh']);

        $code = (new ShortCodeGenerator)->generate();

        $this->assertNotSame('abcdefgh', $code);
        $this->assertDatabaseMissing('short_urls', ['code' => $code]);
    }
}
