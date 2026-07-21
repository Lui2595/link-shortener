<?php

namespace App\Http\Controllers;

use App\Models\ShortUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class RedirectController extends Controller
{
    public function __invoke(Request $request, string $code): Response|JsonResponse|RedirectResponse
    {
        if (! preg_match('/^[a-z0-9]{8}$/', $code)) {
            abort(404);
        }

        $cached = Cache::remember(
            "short_url:{$code}",
            now()->addHour(),
            function () use ($code): ?array {
                $url = ShortUrl::query()->where('code', $code)->first();

                if (! $url) {
                    return null;
                }

                return [
                    'id' => $url->id,
                    'original_url' => $url->original_url,
                    'code' => $url->code,
                ];
            }
        );

        if (! $cached) {
            abort(404);
        }

        ShortUrl::query()->whereKey($cached['id'])->increment('clicks');

        if ($request->expectsJson()) {
            return response()->json([
                'original_url' => $cached['original_url'],
                'code' => $cached['code'],
            ]);
        }

        if ($request->boolean('direct')) {
            return redirect()->away($cached['original_url'], 302);
        }

        return Inertia::render('redirect', [
            'originalUrl' => $cached['original_url'],
            'code' => $cached['code'],
        ]);
    }
}
