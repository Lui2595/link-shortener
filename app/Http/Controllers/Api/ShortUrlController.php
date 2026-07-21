<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShortUrlRequest;
use App\Http\Requests\UpdateShortUrlRequest;
use App\Models\ShortUrl;
use App\Services\ShortCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class ShortUrlController extends Controller
{
    public function __construct(private readonly ShortCodeGenerator $codeGenerator) {}

    #[OA\Get(
        path: '/urls',
        summary: 'List authenticated user short URLs',
        tags: ['URLs'],
        responses: [
            new OA\Response(response: 200, description: 'URL list'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $urls = $request->user()
            ->shortUrls()
            ->latest()
            ->get()
            ->map(fn (ShortUrl $url) => $this->transform($url));

        return response()->json(['data' => $urls]);
    }

    #[OA\Post(
        path: '/urls',
        summary: 'Create a short URL',
        tags: ['URLs'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['original_url'],
                properties: [
                    new OA\Property(property: 'original_url', type: 'string', format: 'uri', example: 'https://spot2.mx/spots/offices'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreShortUrlRequest $request): JsonResponse
    {
        $url = $request->user()->shortUrls()->create([
            'code' => $this->codeGenerator->generate(),
            'original_url' => $request->validated('original_url'),
        ]);

        return response()->json(['data' => $this->transform($url)], 201);
    }

    #[OA\Get(
        path: '/urls/{id}',
        summary: 'Show a short URL',
        tags: ['URLs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Short URL'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Request $request, ShortUrl $url): JsonResponse
    {
        $this->authorizeOwner($request, $url);

        return response()->json(['data' => $this->transform($url)]);
    }

    #[OA\Put(
        path: '/urls/{id}',
        summary: 'Update a short URL destination',
        tags: ['URLs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['original_url'],
                properties: [
                    new OA\Property(property: 'original_url', type: 'string', format: 'uri'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function update(UpdateShortUrlRequest $request, ShortUrl $url): JsonResponse
    {
        $this->authorizeOwner($request, $url);

        $url->update([
            'original_url' => $request->validated('original_url'),
        ]);

        Cache::forget($this->cacheKey($url->code));

        return response()->json(['data' => $this->transform($url->fresh())]);
    }

    #[OA\Delete(
        path: '/urls/{id}',
        summary: 'Delete a short URL',
        tags: ['URLs'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Request $request, ShortUrl $url): JsonResponse
    {
        $this->authorizeOwner($request, $url);

        Cache::forget($this->cacheKey($url->code));
        $url->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwner(Request $request, ShortUrl $url): void
    {
        abort_unless($url->user_id === $request->user()->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(ShortUrl $url): array
    {
        return [
            'id' => $url->id,
            'code' => $url->code,
            'original_url' => $url->original_url,
            'short_url' => url('/'.$url->code),
            'clicks' => $url->clicks,
            'created_at' => $url->created_at?->toIso8601String(),
            'updated_at' => $url->updated_at?->toIso8601String(),
        ];
    }

    private function cacheKey(string $code): string
    {
        return "short_url:{$code}";
    }
}
