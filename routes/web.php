<?php

use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\KehadiranController;
use App\Http\Controllers\Admin\RoleController;
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

    Route::get('/tracking_koordinator_history_location', [TrackingKoordinatorController::class, 'index'])
        ->middleware('hak:tracking_koordinator_history_location');
    Route::get('/tracking_koordinator_history_location/locations', [TrackingKoordinatorController::class, 'locations'])
        ->middleware('hak:tracking_koordinator_history_location')->name('tracking-koordinator.locations');
    Route::get('/tracking_koordinator_history_location/counts', [TrackingKoordinatorController::class, 'counts'])
        ->middleware('hak:tracking_koordinator_history_location')->name('tracking-koordinator.counts');

    Route::get('/tracking_sales_history_location', [TrackingSalesController::class, 'index'])
        ->middleware('hak:tracking_sales_history_location');
    Route::get('/tracking_sales_history_location/locations', [TrackingSalesController::class, 'locations'])
        ->middleware('hak:tracking_sales_history_location')->name('tracking-sales.locations');
    Route::get('/tracking_sales_history_location/counts', [TrackingSalesController::class, 'counts'])
        ->middleware('hak:tracking_sales_history_location')->name('tracking-sales.counts');

    Route::prefix('master_area/design_area')->name('design-area.')->controller(DesignAreaController::class)->group(function () {
        Route::get('/', 'index')->middleware('hak:master_area')->name('index');
        Route::get('/options', 'options')->middleware('hak:master_area')->name('options');
        Route::get('/areas', 'areas')->middleware('hak:master_area')->name('areas');
        Route::post('/areas', 'store')->middleware('hak:master_area,ubah')->name('store');
        Route::delete('/areas/{id}', 'destroy')->middleware('hak:master_area,hapus')->whereNumber('id')->name('destroy');
    });

    // Ikon "Tambah" di kanan atas: formnya belum dibuat di versi Laravel.
    Route::view('/loading_barang/add', 'menu.coming_soon', ['title' => 'Tambah Loading Barang', 'back' => 'loading_barang'])
        ->middleware('hak:loading_barang,tambah');
    Route::view('/barang_canvassing/add', 'menu.coming_soon', ['title' => 'Tambah Barang Canvassing', 'back' => 'barang_canvassing'])
        ->middleware('hak:barang_canvassing,tambah');

    // Kelola akun login (4 menu Users) dan role & hak akses; parameter "menu" = slug menu.
    foreach (array_keys(AkunController::MENU) as $slug) {
        Route::prefix($slug)->name("akun.{$slug}.")->controller(AkunController::class)->group(function () use ($slug) {
            $rute = [
                // [method, uri, aksi controller, hak, nama]
                ['get', '/', 'index', 'lihat', 'index'],
                ['get', '/karyawan', 'cariKaryawan', 'lihat', 'karyawan'],
                ['get', '/tambah', 'create', 'tambah', 'create'],
                ['post', '/', 'store', 'tambah', 'store'],
                ['get', '/{id}', 'show', 'lihat', 'show'],
                ['get', '/{id}/edit', 'edit', 'ubah', 'edit'],
                ['put', '/{id}', 'update', 'ubah', 'update'],
                ['post', '/{id}/password', 'aturPassword', 'ubah', 'password'],
                ['post', '/{id}/status', 'ubahStatus', 'ubah', 'status'],
                ['post', '/{id}/cabut-sesi', 'cabutSesi', 'ubah', 'cabut-sesi'],
                ['post', '/{id}/pakai', 'pakaiAkun', 'ubah', 'pakai'],
                ['delete', '/{id}', 'destroy', 'hapus', 'destroy'],
            ];

            if ($slug === 'user_koordinator') {
                // Atur sales di bawah koordinator.
                $rute[] = ['get', '/{id}/sales/cari', 'cariSales', 'ubah', 'sales.cari'];
                $rute[] = ['post', '/{id}/sales', 'tambahSales', 'ubah', 'sales.tambah'];
                $rute[] = ['delete', '/{id}/sales/{sales}', 'lepasSales', 'ubah', 'sales.lepas'];
            }

            foreach ($rute as [$method, $uri, $aksi, $hak, $nama]) {
                Route::{$method}($uri, $aksi)->middleware("hak:{$slug},{$hak}")
                    ->defaults('menu', $slug)->where(['id' => '[0-9]+', 'sales' => '[0-9]+'])->name($nama);
            }
        });
    }

    // Absensi sales, checkin depo & checkin outlet dari tabel motoris (trx_*), termasuk riwayat aplikasi lama hasil migrasi.
    foreach (['absensi_sales' => 'absensi', 'absensi_sales_terlambat' => 'absensi', 'lap_absensi_sales' => 'absensi',
        'checkin_depo' => 'checkinDepo', 'lap_checkin_checkout' => 'checkinDepo',
        'checkin_outlet' => 'kunjungan', 'lap_checkin_outlet' => 'kunjungan'] as $slug => $aksi) {
        Route::get('/'.$slug, [KehadiranController::class, $aksi])->middleware("hak:{$slug}")
            ->defaults('menu', $slug)->name("kehadiran.{$slug}");
    }
    Route::get('/foto/{id}', [KehadiranController::class, 'foto'])->whereUuid('id')->name('kehadiran.foto');

    Route::prefix('role')->name('role.')->controller(RoleController::class)->group(function () {
        Route::get('/', 'index')->middleware('hak:role')->name('index');
        Route::get('/tambah', 'create')->middleware('hak:role,tambah')->name('create');
        Route::post('/', 'store')->middleware('hak:role,tambah')->name('store');
        Route::get('/{id}/edit', 'edit')->middleware('hak:role')->whereNumber('id')->name('edit');
        Route::put('/{id}', 'update')->middleware('hak:role,ubah')->whereNumber('id')->name('update');
        Route::get('/{id}/anggota', 'anggota')->middleware('hak:role')->whereNumber('id')->name('anggota');
        Route::post('/{id}/pindah', 'pindahkan')->middleware('hak:role,ubah')->whereNumber('id')->name('pindah');
        Route::delete('/{id}', 'destroy')->middleware('hak:role,hapus')->whereNumber('id')->name('destroy');
    });

    Route::get('/{slug}', [MenuController::class, 'show'])
        ->middleware('hak:@slug')
        ->whereIn('slug', array_keys(config('marketama.pages')))
        ->name('menu');
});
