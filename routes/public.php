<?php

use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public property pages
|--------------------------------------------------------------------------
|
| Loaded from bootstrap/app.php with the "web" middleware group.
| {property:slug} binds by the slug column instead of id.
|
*/

Route::get('properties/{property:slug}', [PropertyController::class, 'show'])
    ->name('properties.show');

Route::post('properties/{property:slug}/enquiries', [EnquiryController::class, 'store'])
    // auth: must be logged in. verified: email confirmed, so the agency can reply.
    // throttle:5,1: at most 5 enquiries a minute per user, against bots.
    ->middleware(['auth', 'verified', 'throttle:5,1'])
    ->name('properties.enquiries.store');
