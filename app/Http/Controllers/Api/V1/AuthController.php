<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, AuthService $auth): \Illuminate\Http\JsonResponse
    {
        [$user, $token] = $auth->register($request->validated());

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(LoginRequest $request, AuthService $auth): \Illuminate\Http\JsonResponse
    {
        [$user, $token] = $auth->login($request->validated());

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request, AuthService $auth): \Illuminate\Http\Response
    {
        $user = $request->user();

        if ($user) {
            $auth->logout($user, $user->currentAccessToken());
        }

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}

