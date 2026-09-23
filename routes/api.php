<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| One Sanctum guard serves both actors: the users.role column decides what a
| token may reach. Admin routes manage students, CSV transfers and notices,
| while a student only reaches the profile owned by their own account.
|
| The static /students/import and /students/export routes are registered
| before the resource so they are not captured as a {student} identifier.
|
*/

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');

    Route::middleware('role:admin')->group(function (): void {
        Route::post('/students/import', [ImportExportController::class, 'import'])->name('students.import');
        Route::get('/students/export', [ImportExportController::class, 'export'])->name('students.export');

        Route::apiResource('students', StudentController::class);

        Route::post('/notices/send', [NoticeController::class, 'send'])->name('notices.send');
    });

    Route::middleware('role:student')->group(function (): void {
        Route::get('/my-profile', [StudentController::class, 'myProfile'])->name('students.my-profile');
    });
});
