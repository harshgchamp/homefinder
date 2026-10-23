<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

// Vue practice pages: local only, never deployed.
// app()->isLocal() is true only when APP_ENV=local in .env, so on
// staging/production these routes are never registered (they 404).
if (app()->isLocal()) {
    // middleware('auth')   → visitor must be logged in, otherwise redirected to /login.
    // prefix('practice')   → every URL inside starts with /practice (e.g. /practice/binding).
    // name('practice.')    → every route name inside starts with "practice." so Vue can
    //                        call route('practice.binding') via Ziggy.
    // group(fn)            → applies all three settings above to every route inside.
    Route::middleware('auth')->prefix('practice')->name('practice.')->group(function () {
        // Route::inertia(url, component) is a shortcut for a controller that only does
        // `return Inertia::render('practice/Index');`, no controller class needed.
        // The component name maps to resources/js/pages/practice/Index.vue.
        // URL: /practice   Name: practice.index
        Route::inertia('/', 'practice/Index')->name('index');

        // URL: /practice/binding   Name: practice.binding
        // Renders resources/js/pages/practice/Binding.vue
        Route::inertia('binding', 'practice/Binding')->name('binding');

        // URL: /practice/lists   Name: practice.lists
        // Renders resources/js/pages/practice/Lists.vue
        Route::inertia('lists', 'practice/Lists')->name('lists');

        // URL: /practice/components   Name: practice.components
        // Renders resources/js/pages/practice/Components.vue
        Route::inertia('components', 'practice/Components')->name('components');
    });
}

require __DIR__.'/settings.php';
