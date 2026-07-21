<?php

namespace App\Services;

use App\Models\ShortUrl;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShortCodeGenerator
{
    /**
     * Alphabet without ambiguous characters (0/O, 1/I/l).
     */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    private const LENGTH = 8;

    private const MAX_ATTEMPTS = 10;

    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $this->randomCode();

            if (! ShortUrl::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique short code.');
    }

    public function generateWithinTransaction(): string
    {
        return DB::transaction(fn () => $this->generate());
    }

    private function randomCode(): string
    {
        $alphabetLength = strlen(self::ALPHABET);
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $code;
    }
}
