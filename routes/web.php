<?php

use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\Auth\GantiPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DesignAreaController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\TrackingKoordinatorController;
use App\Http\Controllers\TrackingSalesController;
use Illuminate\Support\Facades\Route;

// Login (hanya untuk tamu) dan logout.
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::post('/login/demo/{peran}', [DemoLoginController::class, 'store'])->name('login.demo');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/ganti-password', [GantiPasswordController::class, 'edit'])->name('password.edit');
    Route::put('/ganti-password', [GantiPasswordController::class, 'update'])->name('password.update');
});

// Semua halaman aplikasi: wajib login, dan akun dengan password awal harus menggantinya dulu.
Route::middleware(['auth', 'wajib-ganti-password'])->group(function () {
    Route::redirect('/', '/home');

    Route::get('/tracking_koordinator_history_location', [TrackingKoordinatorController::class, 'index']);
    Route::get('/tracking_koordinator_history_location/locations', [TrackingKoordinatorController::class, 'locations'])
        ->name('tracking-koordinator.locations');
    Route::get('/tracking_koordinator_history_location/counts', [TrackingKoordinatorController::class, 'counts'])
        ->name('tracking-koordinator.counts');

    Route::get('/tracking_sales_history_location', [TrackingSalesController::class, 'index']);
    Route::get('/tracking_sales_history_location/locations', [TrackingSalesController::class, 'locations'])
        ->name('tracking-sales.locations');
    Route::get('/tracking_sales_history_location/counts', [TrackingSalesController::class, 'counts'])
        ->name('tracking-sales.counts');

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
});
