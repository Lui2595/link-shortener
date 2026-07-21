<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeployService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use RuntimeException;
use Throwable;

class DeployController extends Controller
{
    #[OA\Post(
        path: '/deploy',
        summary: 'Deploy webhook (git pull → tests → build / rollback)',
        description: 'Requires header X-Deploy-Secret matching DEPLOY_SECRET. On test or build failure, resets git to the previous commit.',
        tags: ['Deploy'],
        parameters: [
            new OA\Parameter(
                name: 'X-Deploy-Secret',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deploy succeeded'),
            new OA\Response(response: 401, description: 'Invalid secret'),
            new OA\Response(response: 409, description: 'Deploy already running'),
            new OA\Response(response: 422, description: 'Deploy failed (rolled back when possible)'),
            new OA\Response(response: 503, description: 'Deploy endpoint disabled'),
        ]
    )]
    public function __invoke(Request $request, DeployService $deployer): JsonResponse
    {
        $configured = (string) config('deploy.secret');

        if ($configured === '') {
            return response()->json([
                'message' => 'Deploy endpoint is disabled. Set DEPLOY_SECRET in .env.',
            ], 503);
        }

        $provided = (string) ($request->header('X-Deploy-Secret') ?: $request->input('secret', ''));

        if (! hash_equals($configured, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $result = $deployer->deploy();
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Deploy crashed.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'message' => $result['ok']
                ? 'Deploy completed successfully.'
                : 'Deploy failed.'.($result['rolled_back'] ? ' Rolled back to previous commit.' : ' Rollback failed.'),
            ...$result,
        ], $result['ok'] ? 200 : 422);
    }
}
