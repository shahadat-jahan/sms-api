<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

class AuthController extends Controller
{
    /**
     * Issue a personal access token for valid credentials.
     *
     * Admins and students authenticate through the same endpoint; the
     * `role` column decides which endpoints the token may reach.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if (! $user instanceof User || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('api-token')->plainTextToken,
        ]);
    }

    /**
     * Revoke the token that authenticated the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => new UserResource($user)]);
    }
}
