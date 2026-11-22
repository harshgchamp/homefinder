<?php

use App\Http\Controllers\Tenant\ProjectController;
use Illuminate\Support\Facades\Route;

Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('projects/{project}', [ProjectController::class, 'show'])->whereNumber('project')->name('projects.show');
