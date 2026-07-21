<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'LPshortener API',
    description: 'Passwordless OTP authentication and short URL management.'
)]
#[OA\Server(url: '/api', description: 'API base path')]
#[OA\Tag(name: 'Auth', description: 'OTP login')]
#[OA\Tag(name: 'URLs', description: 'Short URL CRUD')]
#[OA\Tag(name: 'Deploy', description: 'Protected deploy webhook')]
class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    #[OA\Post(
        path: '/auth/otp/request',
        summary: 'Request a one-time login code',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'OTP sent'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 429, description: 'Too many requests'),
        ]
    )]
    public function requestOtp(RequestOtpRequest $request): JsonResponse
    {
        $this->otpService->send($request->validated('email'));

        return response()->json([
            'message' => 'If the email is valid, a login code has been sent.',
        ]);
    }

    #[OA\Post(
        path: '/auth/otp/verify',
        summary: 'Verify OTP and start a session',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'code'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'code', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authenticated'),
            new OA\Response(response: 422, description: 'Invalid or expired code'),
        ]
    )]
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->otpService->verify(
            $request->validated('email'),
            $request->validated('code'),
        );

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Authenticated.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'End the current session',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Logged out'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    #[OA\Get(
        path: '/auth/me',
        summary: 'Current authenticated user',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Current user'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
