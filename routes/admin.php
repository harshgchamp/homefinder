<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// index, create, store, edit, update, destroy. No "show" page for a lookup table.
Route::resource('features', FeatureController::class)
    ->except('show')
    // Rate-limit only the routes that write: 30 requests per minute.
    ->middlewareFor(['store', 'update', 'destroy'], 'throttle:30,1');
