<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON API
|--------------------------------------------------------------------------
|
| Loaded from bootstrap/app.php with the "api" middleware group and the
| /api URL prefix. Stateless: no session, no cookies, no CSRF. Clients
| authenticate with a Sanctum token: Authorization: Bearer {token}.
|
| v1: breaking changes go into a new v2, so old mobile apps keep working.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // throttle:api-login: 5 tries a minute per email + IP (AppServiceProvider).
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('login');

    // auth:sanctum: a valid, unexpired token is required, else 401.
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});
