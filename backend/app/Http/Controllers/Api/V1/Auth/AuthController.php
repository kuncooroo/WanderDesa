<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginWithApiToken;
use App\Actions\Auth\LogoutApiToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\AuthMeResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Device;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginWithApiToken $login): JsonResponse
    {
        $result = $login->handle(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            deviceName: $request->string('device_name')->toString() ?: 'api',
            request: $request,
        );

        return ApiResponse::success([
            'token' => $result['plain_text_token'],
            'token_type' => 'Bearer',
            'user' => (new UserResource($result['user']))->resolve($request),
        ]);
    }

    public function logout(Request $request, LogoutApiToken $logout): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::error(
                'auth.forbidden',
                'Only staff user tokens can log out via this endpoint.',
                403,
            );
        }

        $revoked = $logout->handle($user, $request);

        return ApiResponse::success([
            'revoked' => $revoked,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $principal = $request->user();

        if (! $principal instanceof User && ! $principal instanceof Device) {
            return ApiResponse::error('auth.unauthenticated', 'Unauthenticated.', 401);
        }

        return ApiResponse::success(
            (new AuthMeResource($principal))->resolve($request),
        );
    }
}
