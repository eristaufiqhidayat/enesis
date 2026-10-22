<?php

use App\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/home');

Route::get('/{slug}', [MenuController::class, 'show'])
    ->whereIn('slug', array_keys(config('marketama.pages')))
    ->name('menu');
