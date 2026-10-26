<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\FavouriteController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\TokenController;
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

    // Public: no token. throttle:api counts per IP here.
    Route::middleware('throttle:api')->group(function (): void {
        Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
        Route::get('properties/{property:slug}', [PropertyController::class, 'show'])->name('properties.show');
    });

    // auth:sanctum: a valid, unexpired token is required, else 401.
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');

        // Logged-in devices. whereNumber: /tokens/abc is a 404, not a type error.
        Route::get('tokens', [TokenController::class, 'index'])->name('tokens.index');

        Route::delete('tokens/{tokenId}', [TokenController::class, 'destroy'])
            ->whereNumber('tokenId')
            ->name('tokens.destroy');

        Route::get('favourites', [FavouriteController::class, 'index'])->name('favourites.index');

        // abilities: the TOKEN must also carry this permission, else 403.
        // Login tokens get ['*'] (everything); a read-only token does not.
        Route::middleware('abilities:favourites:write')->group(function (): void {
            Route::put('properties/{property:slug}/favourite', [FavouriteController::class, 'store'])
                ->name('favourites.store');
            Route::delete('properties/{property:slug}/favourite', [FavouriteController::class, 'destroy'])
                ->name('favourites.destroy');
        });
    });
});
