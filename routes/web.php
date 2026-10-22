<?php

use App\Http\Controllers\DesignAreaController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\TrackingKoordinatorController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/home');

Route::get('/tracking_koordinator_history_location', [TrackingKoordinatorController::class, 'index']);
Route::get('/tracking_koordinator_history_location/locations', [TrackingKoordinatorController::class, 'locations'])
    ->name('tracking-koordinator.locations');

Route::prefix('master_area/design_area')->name('design-area.')->controller(DesignAreaController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/options', 'options')->name('options');
    Route::get('/areas', 'areas')->name('areas');
    Route::post('/areas', 'store')->name('store');
    Route::delete('/areas/{id}', 'destroy')->whereNumber('id')->name('destroy');
});

// Ikon "Tambah" di kanan atas: formnya belum dibuat di versi Laravel.
Route::view('/loading_barang/add', 'menu.coming_soon', ['title' => 'Tambah Loading Barang', 'back' => 'loading_barang']);
Route::view('/barang_canvassing/add', 'menu.coming_soon', ['title' => 'Tambah Barang Canvassing', 'back' => 'barang_canvassing']);

Route::get('/{slug}', [MenuController::class, 'show'])
    ->whereIn('slug', array_keys(config('marketama.pages')))
    ->name('menu');
