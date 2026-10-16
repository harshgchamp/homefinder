<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\PropertyExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// index, create, store, edit, update, destroy. No "show" page for a lookup table.
Route::resource('features', FeatureController::class)
    ->except('show')
    // Rate-limit only the routes that write: 30 requests per minute.
    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:30,1');

// --- Properties ---------------------------------------------------------

// Extra routes go BEFORE Route::resource. Otherwise GET /properties/export
// would match the resource's GET /properties/{property} with {property} = "export".
Route::get('properties/export', PropertyExportController::class)
    ->middleware('throttle:10,1') // exports are heavy; 10 per minute
    ->name('properties.export');

Route::post('properties/bulk', [PropertyController::class, 'bulk'])
    ->middleware('throttle:30,1')
    ->name('properties.bulk');

// withTrashed(): route model binding normally ignores soft-deleted rows (404).
// These two routes exist to act on trashed properties, so they must find them.
Route::patch('properties/{property}/restore', [PropertyController::class, 'restore'])
    ->withTrashed()
    ->name('properties.restore');

Route::delete('properties/{property}/force', [PropertyController::class, 'forceDelete'])
    ->withTrashed()
    ->name('properties.force-delete');

Route::resource('properties', PropertyController::class)
    // Only "show" can open a trashed property; edit/update/destroy still 404 for it.
    ->withTrashed(['show'])
    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:30,1');

// --- Notifications --------------------------------------------------------

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
// {id} is a UUID string (notifications use UUID primary keys), so whereUuid()
// rejects anything else before the controller runs.
Route::post('notifications/{id}/read', [NotificationController::class, 'read'])
    ->whereUuid('id')
    ->name('notifications.read');
