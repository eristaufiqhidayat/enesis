<?php

use App\Http\Controllers\Mobile\AbsensiController;
use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\CanvassingController;
use App\Http\Controllers\Mobile\KinerjaController;
use App\Http\Controllers\Mobile\KoordinatorController;
use App\Http\Controllers\Mobile\KunjunganController;
use App\Http\Controllers\Mobile\OutletController;
use App\Http\Controllers\Mobile\PenjualanController;
use App\Http\Controllers\Mobile\ProfilController;
use App\Http\Controllers\Mobile\TrackingController;
use App\Http\Middleware\Mobile\VersiAplikasi;
use Illuminate\Support\Facades\Route;

// API aplikasi mobile Enesis: sales motoris dan koordinator. Prefix /api/mobile/v1, token Bearer
// per HP (guard "mobile"). Kontrak lengkap: docs/api/mobile-v1.yaml.
Route::prefix('mobile/v1')->name('mobile.')->middleware(VersiAplikasi::class)->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->name('login');

    Route::middleware(['auth:mobile', 'throttle:mobile'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('auth/change-password', [AuthController::class, 'gantiPassword'])->name('change-password');
        Route::get('me', [ProfilController::class, 'me'])->name('me');

        // Dipakai sales dan koordinator: aturan hari ini, unggah foto, absen, tracking GPS.
        Route::middleware('mobile.peran:sales,koordinator')->group(function () {
            Route::get('config', [ProfilController::class, 'config'])->name('config');
            Route::post('uploads', [ProfilController::class, 'upload'])->name('uploads');
            Route::get('attendances', [AbsensiController::class, 'index'])->name('attendances');
            Route::post('tracking/batch', [TrackingController::class, 'batch'])->name('tracking.batch');

            Route::middleware('mobile.idempoten')->group(function () {
                Route::post('attendances/check-in', [AbsensiController::class, 'checkIn'])->name('attendances.check-in');
                Route::post('attendances/check-out', [AbsensiController::class, 'checkOut'])->name('attendances.check-out');
            });
        });

        // Koordinator: tim hari ini dan join visit.
        Route::middleware('mobile.peran:koordinator')->group(function () {
            Route::get('team/today', [KoordinatorController::class, 'tim'])->name('team.today');
            Route::get('coordinator/outlets/nearby', [KoordinatorController::class, 'outletTerdekat'])->name('coordinator.outlets.nearby');
            Route::get('join-visits', [KoordinatorController::class, 'riwayat'])->name('join-visits.index');

            Route::middleware('mobile.idempoten')->group(function () {
                Route::post('join-visits', [KoordinatorController::class, 'mulai'])->name('join-visits.store');
                Route::post('join-visits/{id}/finish', [KoordinatorController::class, 'selesai'])->whereNumber('id')->name('join-visits.finish');
            });
        });

        Route::middleware('mobile.peran:sales')->group(function () {
            Route::get('master', [ProfilController::class, 'master'])->name('master');
            Route::get('canvassing/today', [CanvassingController::class, 'muatan'])->name('canvassing.today');
            Route::get('canvassing/{nomor}/balance', [CanvassingController::class, 'sisa'])->name('canvassing.balance');
            Route::get('routes/today', [OutletController::class, 'rute'])->name('routes.today');
            Route::get('outlets/nearby', [OutletController::class, 'terdekat'])->name('outlets.nearby');
            Route::get('outlets/{id}', [OutletController::class, 'detail'])->whereNumber('id')->name('outlets.show');
            Route::get('sales', [PenjualanController::class, 'index'])->name('sales.index');
            Route::get('me/summary', [KinerjaController::class, 'ringkasan'])->name('me.summary');
            Route::get('me/performance', [KinerjaController::class, 'bulanan'])->name('me.performance');
            Route::get('sync/status', [KinerjaController::class, 'sinkron'])->name('sync.status');

            // Transaksi: wajib Idempotency-Key, aman dikirim ulang dari antrian offline.
            Route::middleware('mobile.idempoten')->group(function () {
                Route::post('depot-visits/check-in', [AbsensiController::class, 'depoCheckIn'])->name('depot.check-in');
                Route::post('depot-visits/check-out', [AbsensiController::class, 'depoCheckOut'])->name('depot.check-out');
                Route::post('canvassing/{nomor}/accept', [CanvassingController::class, 'terima'])->name('canvassing.accept');
                Route::post('canvassing/{nomor}/return', [CanvassingController::class, 'kembalikan'])->name('canvassing.return');
                Route::post('outlets', [OutletController::class, 'daftar'])->name('outlets.store');
                Route::post('visits', [KunjunganController::class, 'checkIn'])->name('visits.store');
                Route::post('visits/{id}/stocks', [KunjunganController::class, 'stok'])->whereNumber('id')->name('visits.stocks');
                Route::post('visits/{id}/no-sale', [KunjunganController::class, 'tidakBeli'])->whereNumber('id')->name('visits.no-sale');
                Route::post('visits/{id}/check-out', [KunjunganController::class, 'checkOut'])->whereNumber('id')->name('visits.check-out');
                Route::post('sales', [PenjualanController::class, 'simpan'])->name('sales.store');
                Route::post('sales/{id}/void', [PenjualanController::class, 'batal'])->whereNumber('id')->name('sales.void');
            });
        });
    });
});
