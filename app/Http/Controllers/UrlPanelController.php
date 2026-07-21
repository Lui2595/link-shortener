<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShortUrlRequest;
use App\Http\Requests\UpdateShortUrlRequest;
use App\Models\ShortUrl;
use App\Services\ShortCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class UrlPanelController extends Controller
{
    public function __construct(private readonly ShortCodeGenerator $codeGenerator) {}

    public function index(Request $request): Response
    {
        $urls = $request->user()
            ->shortUrls()
            ->latest()
            ->get()
            ->map(fn (ShortUrl $url) => [
                'id' => $url->id,
                'code' => $url->code,
                'original_url' => $url->original_url,
                'short_url' => url('/'.$url->code),
                'clicks' => $url->clicks,
                'created_at' => $url->created_at?->toIso8601String(),
            ]);

        return Inertia::render('urls/index', [
            'urls' => $urls,
            'highlightId' => $request->session()->pull('highlight_id'),
            'flashSuccess' => $request->session()->get('success'),
        ]);
    }

    public function store(StoreShortUrlRequest $request): RedirectResponse
    {
        if (! $request->user()) {
            $request->session()->put('pending_url', $request->validated('original_url'));

            return redirect()->route('home')->with('otp_required', true);
        }

        $url = $request->user()->shortUrls()->create([
            'code' => $this->codeGenerator->generate(),
            'original_url' => $request->validated('original_url'),
        ]);

        return redirect()
            ->route('urls.index')
            ->with('highlight_id', $url->id)
            ->with('success', 'Short URL created.');
    }

    public function update(UpdateShortUrlRequest $request, ShortUrl $url): RedirectResponse
    {
        abort_unless($url->user_id === $request->user()->id, 404);

        $url->update(['original_url' => $request->validated('original_url')]);
        Cache::forget("short_url:{$url->code}");

        return redirect()->route('urls.index')->with('success', 'URL updated.');
    }

    public function destroy(Request $request, ShortUrl $url): RedirectResponse
    {
        abort_unless($url->user_id === $request->user()->id, 404);

        Cache::forget("short_url:{$url->code}");
        $url->delete();

        return redirect()->route('urls.index')->with('success', 'URL deleted.');
    }
}
