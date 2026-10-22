<?php

use App\Http\Controllers\MenuController;
use App\Http\Controllers\TrackingKoordinatorController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/home');

Route::get('/tracking_koordinator_history_location', [TrackingKoordinatorController::class, 'index']);
Route::get('/tracking_koordinator_history_location/locations', [TrackingKoordinatorController::class, 'locations'])
    ->name('tracking-koordinator.locations');

Route::get('/{slug}', [MenuController::class, 'show'])
    ->whereIn('slug', array_keys(config('marketama.pages')))
    ->name('menu');
