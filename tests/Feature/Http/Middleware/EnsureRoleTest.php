<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('returns 403 when no user is authenticated', function (): void {
    Route::middleware('role:admin')->get('/_test/admin-only', fn (): string => 'ok');

    $this->getJson('/_test/admin-only')->assertForbidden();
});

test('returns 403 when the authenticated user holds another role', function (): void {
    Route::middleware('role:admin')->get('/_test/admin-only', fn (): string => 'ok');

    $this->actingAs(User::factory()->student()->create())
        ->getJson('/_test/admin-only')
        ->assertForbidden();
});

test('allows a user that holds one of the listed roles', function (): void {
    Route::middleware('role:admin,student')->get('/_test/shared', fn (): string => 'ok');

    $this->actingAs(User::factory()->student()->create())
        ->getJson('/_test/shared')
        ->assertOk();
});
