<?php

namespace App\Services;

use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OtpService
{
    private const CODE_LENGTH = 6;

    private const TTL_MINUTES = 10;

    public function send(string $email): void
    {
        $email = Str::lower($email);

        User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => Str::before($email, '@'),
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ],
        );

        $plainCode = $this->generateCode();

        LoginOtp::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        LoginOtp::query()->create([
            'email' => $email,
            'code' => $plainCode,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        Mail::to($email)->send(new LoginOtpMail($plainCode));
    }

    public function verify(string $email, string $code): User
    {
        $email = Str::lower($email);

        $otp = LoginOtp::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $otp || ! $otp->isValid() || ! Hash::check($code, $otp->code)) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid or has expired.'],
            ]);
        }

        $otp->update(['consumed_at' => now()]);

        $user = User::query()->where('email', $email)->firstOrFail();

        Auth::login($user, remember: true);

        return $user;
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
