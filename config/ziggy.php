<?php

/*
|--------------------------------------------------------------------------
| Ziggy
|--------------------------------------------------------------------------
|
| @routes in app.blade.php prints every named route into the page so Vue
| can call route('admin.properties.edit', id). Anyone can read that list
| in the page source. That is acceptable: knowing a URL grants nothing,
| because every admin route is protected by the auth + can:access-admin
| middleware. But there is no reason to publish routes the frontend never
| calls, so they are left out here.
|
*/

return [
    'except' => [
        'storage.*',     // file serving, never linked from Vue
        'well-known.*',  // read by browsers/password managers, not by Vue
        'boost.*',       // Laravel Boost dev tooling
    ],
];
