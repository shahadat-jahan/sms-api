<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

describe('login', function (): void {
    test('returns a token and the user for valid credentials', function (): void {
        User::factory()->admin()->create([
            'email' => 'admin.login@school.test',
            'password' => 'Admin@12345',
        ]);

        $this->postJson(route('login'), [
            'email' => 'admin.login@school.test',
            'password' => 'Admin@12345',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'admin.login@school.test')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token']);

        expect(PersonalAccessToken::query()->count())->toBe(1);
    });

    test('returns 401 for an unknown email', function (): void {
        $this->postJson(route('login'), [
            'email' => 'nobody@school.test',
            'password' => 'Admin@12345',
        ])->assertUnauthorized();
    });

    test('returns 401 for a wrong password', function (): void {
        User::factory()->create(['email' => 'wrong.password@school.test']);

        $this->postJson(route('login'), [
            'email' => 'wrong.password@school.test',
            'password' => 'not-the-password',
        ])->assertUnauthorized();
    });

    test('returns 422 when the payload is empty', function (): void {
        $this->postJson(route('login'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });

    test('returns 429 after five failed attempts for the same email', function (): void {
        User::factory()->create(['email' => 'throttled@school.test']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('login'), [
                'email' => 'throttled@school.test',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson(route('login'), [
            'email' => 'throttled@school.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    });
});

describe('logout', function (): void {
    test('revokes the token that authenticated the request', function (): void {
        $user = User::factory()->admin()->create();
        $plainTextToken = $user->createToken('api-token')->plainTextToken;

        $this->withToken($plainTextToken)->postJson(route('logout'))->assertOk();

        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    test('rejects a token that has been revoked', function (): void {
        $user = User::factory()->admin()->create();
        $newToken = $user->createToken('api-token');

        $newToken->accessToken->delete();

        $this->withToken($newToken->plainTextToken)->getJson(route('me'))->assertUnauthorized();
    });

    test('returns 401 without a token', function (): void {
        $this->postJson(route('logout'))->assertUnauthorized();
    });
});

describe('me', function (): void {
    test('returns the authenticated user', function (): void {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->getJson(route('me'))
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'admin');
    });

    test('returns 401 without a token', function (): void {
        $this->getJson(route('me'))->assertUnauthorized();
    });
});
