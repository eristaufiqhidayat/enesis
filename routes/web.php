<?php

use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\BackupDataController;
use App\Http\Controllers\Admin\KaryawanController;
use App\Http\Controllers\Admin\KehadiranController;
use App\Http\Controllers\Admin\KuotaMotorisController;
use App\Http\Controllers\Admin\MasterProdukController;
use App\Http\Controllers\Admin\MotorisReportController;
use App\Http\Controllers\Admin\RekapPencapaianSkuController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SummaryDepoController;
use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\Auth\GantiPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AutoGridController;
use App\Http\Controllers\BarangCanvassingController;
use App\Http\Controllers\DesignAreaController;
use App\Http\Controllers\GenerateKelurahanController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LapLoadingBarangController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OutletBaruController;
use App\Http\Controllers\PetaSebaranSalesController;
use App\Http\Controllers\TrackingKoordinatorController;
use App\Http\Controllers\TrackingSalesController;
use App\Support\Master\MasterProduk;
use Illuminate\Http\Request;
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

    // Peta sebaran sales se-Indonesia (ikon peta di kanan atas); data = Tracking Last Seen Sales.
    Route::get('/peta_sebaran_sales', [PetaSebaranSalesController::class, 'index'])
        ->middleware('hak:tracking_sales')->name('peta-sebaran-sales.index');
    Route::get('/peta_sebaran_sales/data', [PetaSebaranSalesController::class, 'data'])
        ->middleware('hak:tracking_sales')->name('peta-sebaran-sales.data');
    Route::get('/peta_sebaran_sales/area', [PetaSebaranSalesController::class, 'area'])
        ->middleware('hak:tracking_sales')->name('peta-sebaran-sales.area');

    Route::prefix('master_area/design_area')->name('design-area.')->controller(DesignAreaController::class)->group(function () {
        Route::get('/', 'index')->middleware('hak:master_area')->name('index');
        Route::get('/options', 'options')->middleware('hak:master_area')->name('options');
        Route::get('/areas', 'areas')->middleware('hak:master_area')->name('areas');
        Route::post('/areas', 'store')->middleware('hak:master_area,ubah')->name('store');
        Route::put('/areas/{id}', 'update')->middleware('hak:master_area,ubah')->whereNumber('id')->name('update');
        Route::delete('/areas/{id}', 'destroy')->middleware('hak:master_area,hapus')->whereNumber('id')->name('destroy');
    });

    // Generate area per kelurahan dari batas resmi (URL sama dengan Marketama).
    Route::prefix('master_area/kelurahan_polygon')->name('kelurahan-polygon.')->controller(GenerateKelurahanController::class)->group(function () {
        Route::get('/', 'index')->middleware('hak:master_area')->name('index');
        Route::get('/opsi', 'opsi')->middleware('hak:master_area')->name('opsi');
        Route::get('/pratinjau', 'pratinjau')->middleware('hak:master_area')->name('pratinjau');
        Route::post('/simpan', 'simpan')->middleware('hak:master_area,tambah')->name('simpan');
    });

    // Auto Grid: wilayah dibagi kotak 1–15 km yang dipotong batas kelurahan resmi (URL sama dengan Marketama).
    Route::prefix('master_area/auto_design_area')->name('auto-grid.')->controller(AutoGridController::class)->group(function () {
        Route::get('/', 'index')->middleware('hak:master_area')->name('index');
        Route::get('/pratinjau', 'pratinjau')->middleware('hak:master_area')->name('pratinjau');
        Route::post('/simpan', 'simpan')->middleware('hak:master_area,tambah')->name('simpan');
    });

    // Ikon "Tambah" di kanan atas: formnya belum dibuat di versi Laravel.
    Route::inertia('/loading_barang/add', 'Menu/ComingSoon', [
        'title' => 'Tambah Loading Barang',
        'back' => 'loading_barang',
        'backTitle' => config('marketama.pages.loading_barang.title'),
    ])->middleware('hak:loading_barang,tambah');
    // Form tambah muatan canvassing dari web berupa modal di halaman tabel (Menu/Show.vue); /add tetap ada untuk
    // ikon Topbar dan tautan lama. Beserta opsi depo/sales/produk dan simpannya.
    Route::get('/barang_canvassing/add', fn () => redirect('/barang_canvassing?tambah=1'))
        ->middleware('hak:barang_canvassing,tambah')->name('barang-canvassing.tambah');
    Route::get('/barang_canvassing/opsi', [BarangCanvassingController::class, 'opsi'])
        ->middleware('hak:barang_canvassing,tambah')->name('barang-canvassing.opsi');
    Route::post('/barang_canvassing', [BarangCanvassingController::class, 'store'])
        ->middleware('hak:barang_canvassing,tambah')->name('barang-canvassing.store');
    // Detail produk satu muatan canvassing untuk drawer di tabel barang_canvassing.
    Route::get('/barang_canvassing/{canvassing}/item', [BarangCanvassingController::class, 'item'])
        ->whereNumber('canvassing')->middleware('hak:barang_canvassing')->name('barang-canvassing.item');
    // Ganti daftar item muatan yang masih Disiapkan (ditolak 409 bila sudah diterima motoris).
    Route::put('/barang_canvassing/{canvassing}/item', [BarangCanvassingController::class, 'ubahItem'])
        ->whereNumber('canvassing')->middleware('hak:barang_canvassing,ubah')->name('barang-canvassing.item.ubah');
    // Batalkan (soft delete) muatan yang masih Disiapkan (ditolak 409 bila sudah diterima motoris).
    Route::delete('/barang_canvassing/{canvassing}', [BarangCanvassingController::class, 'batalkan'])
        ->whereNumber('canvassing')->middleware('hak:barang_canvassing,hapus')->name('barang-canvassing.batalkan');

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

    // Absensi sales/koordinator, checkin depo & checkin outlet dari tabel motoris (trx_*), termasuk riwayat aplikasi lama hasil migrasi.
    foreach (['absensi_sales' => 'absensi', 'absensi_sales_terlambat' => 'absensi', 'lap_absensi_sales' => 'absensi', 'absensi_koordinator' => 'absensi', 'lap_absensi_koordinator' => 'absensi',
        'checkin_depo' => 'checkinDepo', 'lap_checkin_checkout' => 'checkinDepo',
        'checkin_outlet' => 'kunjungan', 'lap_checkin_outlet' => 'kunjungan'] as $slug => $aksi) {
        Route::get('/'.$slug, [KehadiranController::class, $aksi])->middleware("hak:{$slug}")
            ->defaults('menu', $slug)->name("kehadiran.{$slug}");
    }
    Route::get('/foto/{id}', [KehadiranController::class, 'foto'])->whereUuid('id')->name('kehadiran.foto');

    // Beranda: dashboard interaktif ringkasan nasional data motoris (KPI, funnel outlet, tren harian, depo
    // teratas). Rute sendiri (bukan catch-all MenuController@show) karena "home" tidak lagi sekadar tabel
    // kosong, lihat HomeController. Dideklarasikan sebelum catch-all /{slug} supaya rute ini yang dipakai.
    Route::get('/home', [HomeController::class, 'index'])->middleware('hak:home')->name('home');

    // Motoris Report (Coverage, Performance, Absensi) dihitung dari tabel motoris + kuota isian admin.
    Route::get('/motoris_report', [MotorisReportController::class, 'index'])->middleware('hak:motoris_report')->name('motoris-report');
    Route::get('/kuota_motoris', [KuotaMotorisController::class, 'index'])->middleware('hak:kuota_motoris')->name('kuota-motoris.index');
    Route::post('/kuota_motoris', [KuotaMotorisController::class, 'simpan'])->middleware('hak:kuota_motoris,ubah')->name('kuota-motoris.simpan');

    // Backup data per tabel (CSV / SQL); buat & unduh butuh hak ekspor.
    Route::prefix('backup_data')->name('backup-data.')->controller(BackupDataController::class)->group(function () {
        Route::get('/', 'index')->middleware('hak:backup_data')->name('index');
        Route::post('/', 'store')->middleware('hak:backup_data,ekspor')->name('store');
        Route::get('/{id}/unduh', 'unduh')->middleware('hak:backup_data,ekspor')->whereNumber('id')->name('unduh');
        Route::delete('/{id}', 'destroy')->middleware('hak:backup_data,hapus')->whereNumber('id')->name('destroy');
    });

    // Rekap Pencapaian SKU (Per Motoris & Per Depo) dihitung dari penjualan aplikasi motoris + target global.
    Route::get('/rekap_pencapaian_sku', [RekapPencapaianSkuController::class, 'index'])->middleware('hak:rekap_pencapaian_sku')->name('rekap-sku');
    Route::post('/rekap_pencapaian_sku/target', [RekapPencapaianSkuController::class, 'simpanTarget'])->middleware('hak:rekap_pencapaian_sku,ubah')->name('rekap-sku.target');

    // Summary All Depo (kartu per depo + detail per koordinator) dari tabel motoris + target nasional.
    Route::get('/summary_all_depo', [SummaryDepoController::class, 'index'])->middleware('hak:summary_all_depo')->name('summary-depo');
    Route::get('/summary_all_depo/{depo}', [SummaryDepoController::class, 'detail'])->middleware('hak:summary_all_depo')->whereNumber('depo')->name('summary-depo.detail');
    Route::get('/summary_all_depo/{depo}/penjualan', [SummaryDepoController::class, 'penjualan'])->middleware('hak:summary_all_depo')->whereNumber('depo')->name('summary-depo.penjualan');
    Route::post('/summary_all_depo/target', [SummaryDepoController::class, 'simpanTarget'])->middleware('hak:summary_all_depo,ubah')->name('summary-depo.target');

    // Verifikasi outlet baru dari HP (menu All Outlet, tab Outlet Baru dari HP).
    Route::prefix('outlet_all/baru/{id}')->name('outlet-baru.')->whereNumber('id')->controller(OutletBaruController::class)->group(function () {
        Route::post('/setujui', 'setujui')->middleware('hak:outlet_all,ubah')->name('setujui');
        Route::post('/tolak', 'tolak')->middleware('hak:outlet_all,ubah')->name('tolak');
    });

    // Riwayat transaksi satu distributor untuk drawer di Lap. Loading Barang (lihat Menu/Show.vue).
    Route::get('/lap_loading_barang/riwayat-distributor', [LapLoadingBarangController::class, 'riwayatDistributor'])
        ->middleware('hak:lap_loading_barang')->name('lap-loading-barang.riwayat-distributor');

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

    // Karyawan (Sales, Koordinator, Supervisor, Lainnya): CRUD mk_karyawan; parameter "menu" = slug menu.
    foreach (array_keys(KaryawanController::MENU) as $slug) {
        Route::prefix($slug)->name("karyawan.{$slug}.")->controller(KaryawanController::class)->group(function () use ($slug) {
            $rute = [
                // [method, uri, aksi controller, hak, nama]
                ['get', '/', 'index', 'lihat', 'index'],
                ['get', '/ekspor', 'ekspor', 'ekspor', 'ekspor'],
                ['get', '/opsi', 'opsi', 'lihat', 'opsi'],
                ['post', '/baca-ktp', 'bacaKtp', 'tambah', 'baca-ktp'],
                ['get', '/tambah', 'create', 'tambah', 'create'],
                ['post', '/', 'store', 'tambah', 'store'],
                ['get', '/{id}/edit', 'edit', 'lihat', 'edit'],
                ['get', '/{id}/foto', 'foto', 'lihat', 'foto'],
                ['put', '/{id}', 'update', 'ubah', 'update'],
                ['delete', '/{id}', 'destroy', 'hapus', 'destroy'],
            ];

            if ($slug === 'karyawan_sales') {
                $rute[] = ['post', '/{id}/pindah-area', 'pindahArea', 'ubah', 'pindah-area'];
            }

            foreach ($rute as [$method, $uri, $aksi, $hak, $nama]) {
                Route::{$method}($uri, $aksi)->middleware("hak:{$slug},{$hak}")
                    ->defaults('menu', $slug)->whereNumber('id')->name($nama);
            }
        });
    }

    // Master produk (Produk, Produk Kompetitor, Kategori, Brand, Satuan): CRUD tabel mk_*; parameter "menu" = slug menu.
    foreach (array_keys(MasterProduk::DEFINISI) as $slug) {
        Route::prefix($slug)->name("master-produk.{$slug}.")->controller(MasterProdukController::class)->group(function () use ($slug) {
            foreach ([
                // [method, uri, aksi controller, hak, nama]
                ['get', '/', 'index', 'lihat', 'index'],
                ['get', '/ekspor', 'ekspor', 'ekspor', 'ekspor'],
                ['get', '/tambah', 'create', 'tambah', 'create'],
                ['post', '/', 'store', 'tambah', 'store'],
                ['get', '/{id}/edit', 'edit', 'lihat', 'edit'],
                ['get', '/{id}/foto', 'foto', 'lihat', 'foto'],
                ['put', '/{id}', 'update', 'ubah', 'update'],
                ['delete', '/{id}', 'destroy', 'hapus', 'destroy'],
            ] as [$method, $uri, $aksi, $hak, $nama]) {
                Route::{$method}($uri, $aksi)->middleware("hak:{$slug},{$hak}")
                    ->defaults('menu', $slug)->whereNumber('id')->name($nama);
            }
        });
    }

    // Redirect slug lama menu yang sudah digabung jadi satu halaman bertab; query string ikut diteruskan.
    // Hak dicek di sini juga (sama dengan menu tujuan) supaya pengecekan bolehWeb tidak terlewat di hop redirect ini.
    $redirectSlugLama = function (string $slugBaru, array $tabPerSlugLama) {
        foreach ($tabPerSlugLama as $slugLama => $tab) {
            Route::get("/{$slugLama}", fn (Request $request) => redirect("/{$slugBaru}?".http_build_query(
                [...$request->query(), 'tab' => $tab]
            )))->middleware("hak:{$slugBaru}");
        }
    };

    $redirectSlugLama('monitoring_harian_sales', [
        'checkin_pagi' => 'checkin_pagi',
        'sales_tidak_penjualan' => 'sales_tidak_penjualan',
        'sales_tidak_absen' => 'sales_tidak_absen',
    ]);

    $redirectSlugLama('rekap_pencapaian_sku', [
        'rekap_pencapaian_sku_motoris' => 'motoris',
        'rekap_pencapaian_depo_sku' => 'depo',
    ]);

    $redirectSlugLama('motoris_report', [
        'motoris_coverage' => 'coverage',
        'motoris_performance' => 'performance',
        'monitor_absensi_motoris' => 'absensi',
    ]);

    Route::get('/{slug}', [MenuController::class, 'show'])
        ->middleware('hak:@slug')
        ->whereIn('slug', array_keys(config('marketama.pages')))
        ->name('menu');
});
