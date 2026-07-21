<?php

namespace Database\Seeders;

use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demo = User::query()->firstOrCreate(
            ['email' => 'demo@lpshortener.test'],
            [
                'name' => 'Demo LPshortener',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        ShortUrl::query()->firstOrCreate(
            ['code' => 'demosp2t'],
            [
                'user_id' => $demo->id,
                'original_url' => 'https://example.com',
            ],
        );
    }
}
