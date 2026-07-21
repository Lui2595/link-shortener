<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requests_otp_and_creates_user_if_missing(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/otp/request', [
            'email' => 'new@spot2.mx',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('users', ['email' => 'new@spot2.mx']);
        $this->assertDatabaseCount('login_otps', 1);
        Mail::assertSent(LoginOtpMail::class);
    }

    public function test_it_verifies_otp_and_authenticates(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/otp/request', [
            'email' => 'user@spot2.mx',
        ])->assertOk();

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $response = $this->postJson('/api/auth/otp/verify', [
            'email' => 'user@spot2.mx',
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'user@spot2.mx');

        $this->assertAuthenticated();
    }

    public function test_it_rejects_invalid_otp(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/otp/request', [
            'email' => 'user@spot2.mx',
        ]);

        $this->postJson('/api/auth/otp/verify', [
            'email' => 'user@spot2.mx',
            'code' => '000000',
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_me_and_logout_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->actingAs($user)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertGuest();
    }
}
