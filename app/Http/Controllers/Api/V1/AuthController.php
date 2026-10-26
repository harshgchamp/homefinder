<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token login for API clients (mobile apps, Postman). The API has no session,
 * so the client sends the token on every request instead:
 *   Authorization: Bearer {token}
 */
class AuthController extends Controller
{
    /**
     * Swap email + password for a token.
     *
     * Not handled: users with 2FA enabled log in with the password alone here.
     * A production API would refuse them or add a second "two-factor" step.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var array{email: string, password: string, device_name: string} $data */
        $data = $request->validated();

        $user = User::query()->where('email', $data['email'])->first();

        // Same message for "no such user" and "wrong password": a different
        // message would tell an attacker which emails are registered.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // createToken(name, abilities, expiresAt). The database keeps only a
        // SHA-256 hash; plainTextToken ("{id}|{random}") is shown this once.
        $token = $user->createToken(
            $data['device_name'],
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration')),
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user),
        ]);
    }

    /**
     * The owner of the token. auth:sanctum has already found them.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Delete only the token used for THIS request: this device is logged out,
     * the user's other devices stay logged in.
     */
    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->currentAccessToken()->delete();

        return response()->noContent(); // 204
    }

    /**
     * Delete every token the user has: all devices are logged out, including
     * this one. "I think my account was hacked" / after a password change.
     */
    public function logoutAll(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->tokens()->delete();

        return response()->noContent();
    }
}
