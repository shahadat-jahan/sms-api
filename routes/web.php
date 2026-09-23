<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This application only serves a JSON API (see routes/api.php); the React
| frontend lives in its own project and calls /api over HTTP. The root route
| therefore answers with a small discovery payload instead of a view.
|
*/

Route::get('/', function (): JsonResponse {
    return response()->json([
        'name' => (string) config('app.name'),
        'api' => url('/api'),
        'health' => url('/up'),
        'documentation' => 'See README.md for the endpoint list.',
    ]);
})->name('home');
