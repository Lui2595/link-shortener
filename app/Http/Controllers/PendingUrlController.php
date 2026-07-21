<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShortUrlRequest;
use App\Services\ShortCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PendingUrlController extends Controller
{
    public function __construct(private readonly ShortCodeGenerator $codeGenerator) {}

    public function store(StoreShortUrlRequest $request): RedirectResponse
    {
        if (! $request->user()) {
            $request->session()->put('pending_url', $request->validated('original_url'));

            return redirect()->route('home')->with('otp_required', true);
        }

        return $this->createFromPending($request, $request->validated('original_url'));
    }

    public function commit(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull('pending_url');

        if (! $pending || ! $request->user()) {
            return redirect()->route('urls.index');
        }

        return $this->createFromPending($request, $pending);
    }

    private function createFromPending(Request $request, string $originalUrl): RedirectResponse
    {
        $url = $request->user()->shortUrls()->create([
            'code' => $this->codeGenerator->generate(),
            'original_url' => $originalUrl,
        ]);

        return redirect()
            ->route('urls.index')
            ->with('highlight_id', $url->id)
            ->with('success', 'Short URL created.');
    }
}
