<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * @param  array{name:string,email:string,password:string,device_name?:string}  $payload
     * @return array{0:User,1:string}
     */
    public function register(array $payload): array
    {
        $user = User::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
        ]);

        $token = $user->createToken($payload['device_name'] ?? 'api')->plainTextToken;

        return [$user, $token];
    }

    /**
     * @param  array{email:string,password:string,device_name?:string}  $payload
     * @return array{0:User,1:string}
     */
    public function login(array $payload): array
    {
        $user = User::query()->where('email', $payload['email'])->first();

        if (! $user || ! Hash::check($payload['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $token = $user->createToken($payload['device_name'] ?? 'api')->plainTextToken;

        return [$user, $token];
    }

    public function logout(User $user, ?PersonalAccessToken $currentToken): void
    {
        if ($currentToken) {
            $currentToken->delete();
            return;
        }

        $user->tokens()->delete();
    }
}

