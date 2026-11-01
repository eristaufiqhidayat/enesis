<?php

use App\Support\Menu\Motoris\SumberBarangHabisTerjual;
use App\Support\Menu\Motoris\SumberBarangTerbaikBulanan;
use App\Support\Menu\Motoris\SumberBarangTerbaikHarian;
use App\Support\Menu\Motoris\SumberBarangTerbaikMingguan;
use App\Support\Menu\Motoris\SumberCanvassing;
use App\Support\Menu\Motoris\SumberCanvassingMuatan;
use App\Support\Menu\Motoris\SumberEffectiveCall;
use App\Support\Menu\Motoris\SumberEffectiveCallItem;
use App\Support\Menu\Motoris\SumberInsentifSales;
use App\Support\Menu\Motoris\SumberLastSeenKoordinator;
use App\Support\Menu\Motoris\SumberLastSeenSales;
use App\Support\Menu\Motoris\SumberOutletAc;
use App\Support\Menu\Motoris\SumberOutletEc;
use App\Support\Menu\Motoris\SumberOutletNoo;
use App\Support\Menu\Motoris\SumberOutletOss;
use App\Support\Menu\Motoris\SumberOutletRo;
use App\Support\Menu\Motoris\SumberOutletStatus;
use App\Support\Menu\Motoris\SumberPengembalianBarang;
use App\Support\Menu\Motoris\SumberPenjualan;
use App\Support\Menu\Motoris\SumberPenjualanItem;
use App\Support\Menu\Motoris\SumberSesiLogin;
use App\Support\Menu\Motoris\SumberStokKompetitor;
use App\Support\Menu\Motoris\SumberStokOutlet;
use App\Support\Menu\Motoris\SumberTandaMotoris;
use App\Support\Menu\SumberCheckinPagi;
use App\Support\Menu\SumberJoinVisit;
use App\Support\Menu\SumberMasterArea;
use App\Support\Menu\SumberOutletBaru;
use App\Support\Menu\SumberOutletMaster;

// Struktur menu sidebar disamakan dengan Marketama Canvassing System.
// Dihasilkan dari hasil penarikan data 22 Okt 2026.

return [
    // Menu yang sudah terhubung ke data aplikasi motoris: membaca/menulis tabel yang sama dengan API HP
    // (mk_*, trx_*, akun & role) atau tabel referensi yang dipakai aplikasi HP. Menu lain tampil di sidebar
    // dengan awalan "* " (belum terhubung ke motoris / tidak berhubungan). Tambahkan slug di sini saat menunya
    // dialihkan ke data motoris.
    'menu_motoris' => [
        // Utama
        'monitoring_harian_sales', 'rekap_pencapaian_sku', 'summary_all_depo', 'motoris_report',
        // Canvassing
        'checkin_depo', 'lap_checkin_checkout', 'tracking_koordinator_history_location',
        // Aktivitas Canvas
        'checkin_outlet', 'lap_checkin_outlet',
        // Absensi
        'absensi_sales', 'absensi_sales_terlambat', 'absensi_koordinator', 'lap_absensi_sales',
        // Outlet dan Visit
        'outlet_all', 'join_visit',
        // User dan Karyawan (akun login aplikasi HP, role & hak akses)
        'user_sales', 'user_koordinator', 'role',
        // Master Data & Pengaturan yang dibaca aplikasi HP / laporan motoris
        'kuota_motoris', 'master_depo', 'master_area', 'holiday_dates', 'waktu_aktif_app', 'produk_kompetitor', 'tipe_outlet',
        'rumus_insentif_sales', 'sales_point_conversion_per_warehouse',
        // Master produk (CRUD tabel mk_*, dibaca aplikasi HP lewat /api/mobile/v1/master)
        'produk', 'kategori_produk', 'merk_produk', 'satuan_produk',
        // Karyawan (CRUD mk_karyawan, dipakai aplikasi HP, akun login, dan laporan motoris)
        'karyawan_sales', 'karyawan_koordinator', 'karyawan_supervisor', 'karyawan_lainnya',
        // Grup A analisa menu (25 Okt 2026): dialihkan ke trx_* / mk_* (riwayat lama lewat enesis:migrasi-transaksi)
        'barang_canvassing', 'lap_barang_canvassing', 'lap_barang_habis_terjual', 'pengembalian_barang', 'lap_pengembalian_barang',
        'tracking_sales', 'tracking_koordinator', 'motoris_ditandai',
        'penjualan_barang', 'lap_penjualan_barang', 'lap_penjualan_barang_detail', 'lap_effective_call', 'lap_effective_call_barang',
        'lap_penjualan_barang_terbaik_harian', 'lap_penjualan_barang_terbaik_mingguan', 'lap_penjualan_barang_terbaik_bulanan',
        'stok_outlet', 'lap_stok_outlet', 'stok_kompetitor', 'lap_stok_kompetitor',
        'outlet_noo', 'lap_outlet_noo', 'outlet_ac', 'lap_outlet_ac', 'outlet_ec', 'lap_outlet_ec', 'outlet_ro', 'lap_outlet_ro',
        'outlet_oss', 'lap_outlet_oss', 'lap_outlet_all',
        'lap_join_visit', 'lap_absensi_koordinator', 'login_session', 'lap_insentif_sales',
        // Mencadangkan tabel motoris (trx_*, mk_*) bersama tabel lain.
        'backup_data',
    ],

    // Slug yang menentukan boleh tidaknya akun membuka modal Riwayat dari tabel (shared prop slugRiwayat).
    'slug_riwayat' => ['tracking_sales', 'tracking_sales_history_location', 'tracking_koordinator_history_location'],

    // Halaman terpisah (rute & hak sendiri) yang saling terhubung lewat baris tab di atas halaman:
    // satu kelompok = slug => label tab, dalam urutan tab. Tab hanya memuat halaman yang boleh dibuka akun
    // (lihat Akses::tabHalaman); halaman Last Seen juga memakai pasangan riwayatnya untuk tautan "Lihat riwayat".
    // Di sidebar, slug pertama tiap kelompok (induk) mewakili seluruh kelompok dengan 'judul_sidebar'; slug lainnya
    // disembunyikan selama induknya bisa dibuka akun (lihat Akses::satukanTab). Matriks hak role tetap per slug.
    'tab_halaman' => [
        ['tracking_sales' => 'Last Seen', 'motoris_ditandai' => 'Ditandai'],
        ['tracking_koordinator' => 'Last Seen', 'tracking_koordinator_history_location' => 'Riwayat Lokasi'],
    ],

    // Ambang indikasi GPS tidak wajar per motoris per tanggal (App\Support\Tracking\AnomaliGps). Lompatan = jarak antar dua
    // titik berurutan >= jarak_km dan kecepatan implisit > kecepatan_kmh; kembali ke asal = titik berikutnya < kembali_km dari
    // titik sebelum lompatan. Peringatan "indikasi" muncul bila lompatan >= min_lompatan dan yang kembali ke asal >= min_kembali.
    'anomali_gps' => [
        'jarak_km' => 3,
        'kecepatan_kmh' => 120,
        'kembali_km' => 0.5,
        'min_lompatan' => 5,
        'min_kembali' => 3,
        // Titik menyimpang tunggal: >= menyimpang_km dari titik sebelum dan sesudahnya, sementara kedua tetangga itu saling
        // < tetangga_km. Peringatan "indikasi" (selain aturan lompatan) muncul bila jumlahnya >= min_menyimpang.
        'menyimpang_km' => 3,
        'tetangga_km' => 1,
        'min_menyimpang' => 3,
    ],

    // Jendela (hari, termasuk hari ini) tanda aktif yang dihitung di lencana "Ditandai" Lacak Motoris dan daftar "Tanda di tanggal lain".
    'tanda_motoris_hari' => 30,

    // Bawaan ambang indikasi kunjungan berturutan berjarak dekat (spec 054, spec 058). Nilai yang berlaku disimpan di
    // localStorage browser pengguna (tombol Ambang di modal Riwayat Lacak Motoris) dan dikirim bersama permintaan locations;
    // angka ini dipakai AnomaliJarakKunjungan hanya bila permintaan tidak membawanya (harus sama dengan bawaan di
    // resources/js/Support/deteksiKunjungan.js).
    'deteksi_jarak_kunjungan' => [
        'jarak_dekat_meter' => 11,
        'min_kunjungan_berturutan' => 3,
    ],

    // Kategori tanda motoris (kode => label) yang diberikan koordinator dari drawer Riwayat Lacak Motoris (TandaMotorisController).
    'tanda_motoris' => [
        'penjualan_fiktif' => 'Penjualan fiktif',
        'lokasi_palsu' => 'Lokasi / GPS palsu',
        'kunjungan_tidak_wajar' => 'Kunjungan tidak wajar',
        'potensi_kecurangan' => 'Potensi kecurangan lainnya',
    ],

    // Kategori tanda outlet (kode => label), melekat ke outlet (mk_outlet) dari drawer Riwayat Lacak Motoris atau menu
    // All Outlet (TandaOutletController).
    'tanda_outlet' => [
        'outlet_fiktif' => 'Outlet fiktif',
        'alamat_tidak_sesuai' => 'Alamat/lokasi tidak sesuai',
        'pola_kunjungan_tidak_wajar' => 'Pola kunjungan tidak wajar',
        'potensi_kecurangan' => 'Potensi kecurangan lainnya',
    ],

    'menu' => [
        [
            'label' => null,
            'items' => [
                [
                    'slug' => 'home',
                    'title' => 'Beranda',
                    'icon' => 'home',
                ],
                [
                    'slug' => 'monitoring_harian_sales',
                    'title' => 'Monitoring Harian Sales',
                    'icon' => 'clock',
                ],
                [
                    'slug' => 'rekap_pencapaian_sku',
                    'title' => 'Rekap Pencapaian SKU',
                    'icon' => 'boxes',
                ],
                [
                    'slug' => 'summary_all_depo',
                    'title' => 'Summary All Depo',
                    'icon' => 'chart-pie',
                ],
                [
                    'slug' => 'motoris_report',
                    'title' => 'Motoris Report',
                    'icon' => 'bike',
                ],
            ],
        ],
        [
            'label' => 'Canvassing',
            'icon' => 'megaphone',
            'items' => [
                [
                    'slug' => 'checkin_depo',
                    'title' => 'Absensi Datang & Pulang Depo',
                    'icon' => 'camera',
                ],
                [
                    'slug' => 'template_barang_canvassing',
                    'title' => 'Template Barang Canvassing',
                    'icon' => 'clipboard-list',
                ],
                [
                    'slug' => 'barang_canvassing',
                    'title' => 'Daftar Muatan Motoris',
                    'icon' => 'bike',
                ],
                [
                    'slug' => 'pengembalian_barang',
                    'title' => 'Pengembalian Barang',
                    'icon' => 'undo',
                ],
                [
                    'slug' => 'tracking_sales',
                    'title' => 'Tracking Last Seen Sales',
                    'judul_sidebar' => 'Lacak Motoris',
                    'icon' => 'map-pin',
                ],
                [
                    // Hak melihat modal Riwayat di Lacak Motoris (endpoint `locations`); tidak punya halaman sendiri.
                    'slug' => 'tracking_sales_history_location',
                    'title' => 'Riwayat Lokasi Motoris',
                    'icon' => 'map-pin',
                    'tanpa_halaman' => true,
                ],
                [
                    'slug' => 'motoris_ditandai',
                    'title' => 'Motoris Ditandai',
                    'icon' => 'flag',
                ],
                [
                    'slug' => 'tracking_koordinator',
                    'title' => 'Tracking Last Seen Koordinator',
                    'judul_sidebar' => 'Tracking Koordinator',
                    'icon' => 'map-pin',
                ],
                [
                    'slug' => 'tracking_koordinator_history_location',
                    'title' => 'Tracking History Koordinator Location',
                    'icon' => 'map-pin',
                ],
                [
                    'title' => 'Laporan',
                    'icon' => 'book',
                    'id' => 'lapcanvassing',
                    'children' => [
                        [
                            'slug' => 'lap_checkin_checkout',
                            'title' => 'Lap. Checkin Checkout',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_barang_canvassing',
                            'title' => 'Lap. Barang Canvassing',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_barang_habis_terjual',
                            'title' => 'Lap. Barang Habis Terjual',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_pengembalian_barang',
                            'title' => 'Lap. Pengembalian Barang',
                            'icon' => null,
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Warehouse',
            'icon' => 'warehouse',
            'items' => [
                [
                    'slug' => 'loading_barang',
                    'title' => 'Loading Barang',
                    'icon' => 'shopping-cart',
                ],
                [
                    'slug' => 'retur_barang',
                    'title' => 'Retur Barang',
                    'icon' => 'camera',
                ],
                [
                    'slug' => 'pembayaran_pembelian',
                    'title' => 'Setoran',
                    'icon' => 'banknote',
                ],
                [
                    'slug' => 'setor_margin',
                    'title' => 'Setor Margin',
                    'icon' => 'banknote',
                ],
                [
                    'slug' => 'distributor',
                    'title' => 'Distributor',
                    'icon' => 'warehouse',
                ],
                [
                    'title' => 'Laporan',
                    'icon' => 'book',
                    'id' => 'lappurchasing',
                    'children' => [
                        [
                            'slug' => 'lap_loading_barang',
                            'title' => 'Lap. Loading Barang',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_loading_barang_detail',
                            'title' => 'Lap. Loading Barang Detail',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_retur_barang',
                            'title' => 'Lap. Retur Barang',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_retur_barang_detail',
                            'title' => 'Lap. Retur Barang Detail',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_pembayaran_tagihan',
                            'title' => 'Lap. Setoran',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_setor_margin',
                            'title' => 'Lap. Setor Margin',
                            'icon' => null,
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Aktivitas Canvas',
            'icon' => 'route',
            'items' => [
                [
                    'slug' => 'checkin_outlet',
                    'title' => 'Checkin Outlet',
                    'icon' => 'camera',
                ],
                [
                    'slug' => 'penjualan_barang',
                    'title' => 'Penjualan Barang',
                    'icon' => 'box',
                ],
                [
                    'slug' => 'stok_outlet',
                    'title' => 'Stok Outlet',
                    'icon' => 'store',
                ],
                [
                    'slug' => 'stok_kompetitor',
                    'title' => 'Stok Kompetitor',
                    'icon' => 'store',
                ],
                [
                    'title' => 'Laporan',
                    'icon' => 'book',
                    'id' => 'laptransaksi',
                    'children' => [
                        [
                            'slug' => 'lap_checkin_outlet',
                            'title' => 'Lap. Checkin Outlet',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_effective_call',
                            'title' => 'Lap. Efective Call',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_effective_call_barang',
                            'title' => 'Lap. Efective Call Detail',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penjualan_barang',
                            'title' => 'Lap. Penjualan Barang',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penjualan_barang_detail',
                            'title' => 'Lap. Penjualan Barang Detail',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penjualan_barang_terbaik_harian',
                            'title' => 'Lap. Barang Terbaik Harian',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penjualan_barang_terbaik_mingguan',
                            'title' => 'Lap. Barang Terbaik Mingguan',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penjualan_barang_terbaik_bulanan',
                            'title' => 'Lap. Barang Terbaik Bulanan',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_stok_outlet',
                            'title' => 'Lap. Stok Outlet',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_stok_kompetitor',
                            'title' => 'Lap. Stok Kompetitor',
                            'icon' => null,
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Absensi dan Penggajian',
            'icon' => 'calendar-check',
            'items' => [
                [
                    'slug' => 'absensi_sales',
                    'title' => 'Absensi Sales',
                    'icon' => 'calendar',
                ],
                [
                    'slug' => 'absensi_sales_terlambat',
                    'title' => 'Absensi Sales Terlambat',
                    'icon' => 'clock',
                ],
                [
                    'slug' => 'absensi_koordinator',
                    'title' => 'Absensi Koordinator',
                    'icon' => 'calendar',
                ],
                [
                    'slug' => 'absensi_ssk_supervisor',
                    'title' => 'Absensi SSK / Supervisor',
                    'icon' => 'calendar-check',
                ],
                [
                    'slug' => 'permintaan_izin',
                    'title' => 'Permintaan Izin',
                    'icon' => 'signature',
                ],
                [
                    'title' => 'Penggajian',
                    'icon' => 'file-text',
                    'id' => 'penggajian',
                    'children' => [
                        [
                            'slug' => 'parameter_pemotong_gaji',
                            'title' => 'Parameter Pemotong Gaji',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'potongan_gaji',
                            'title' => 'Potongan Gaji',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'penggajian_sales',
                            'title' => 'Penggajian Sales',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'penggajian_koordinator',
                            'title' => 'Penggajian Koordinator',
                            'icon' => null,
                        ],
                    ],
                ],
                [
                    'title' => 'Laporan',
                    'icon' => 'book',
                    'id' => 'lappenggajian',
                    'children' => [
                        [
                            'slug' => 'lap_absensi_sales',
                            'title' => 'Lap. Absensi Sales',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_absensi_koordinator',
                            'title' => 'Lap. Absensi Koordinator',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_izin_sakit',
                            'title' => 'Lap. Izin/Sakit',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_insentif_sales',
                            'title' => 'Lap. Insentif Sales',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_insentif_koordinator',
                            'title' => 'Lap. Insentif Koordinator',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penggajian_sales',
                            'title' => 'Lap. Penggajian Sales',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penggajian_koordinator',
                            'title' => 'Lap. Penggajian Koordinator',
                            'icon' => null,
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Outlet dan Visit',
            'icon' => 'store',
            'items' => [
                [
                    'title' => 'Outlet',
                    'icon' => 'book',
                    'id' => 'groupoutlet',
                    'children' => [
                        [
                            'slug' => 'outlet_noo',
                            'title' => 'New Open Outlet',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_all',
                            'title' => 'All Outlet',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_ac',
                            'title' => 'Actual Call',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_ec',
                            'title' => 'Effective Call',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_ro',
                            'title' => 'Repeat Order',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_oss',
                            'title' => 'Out of Stock',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_duplikat',
                            'title' => 'Outlet Duplikat / Fiktif',
                            'icon' => 'copy',
                        ],
                    ],
                ],
                [
                    'slug' => 'join_visit',
                    'title' => 'Join Visit',
                    'icon' => 'map',
                ],
                [
                    'title' => 'Laporan',
                    'icon' => 'book',
                    'id' => 'lapoutlet',
                    'children' => [
                        [
                            'slug' => 'lap_outlet_noo',
                            'title' => 'Lap. Outlet NOO',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_all',
                            'title' => 'Lap. Outlet All',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_ac',
                            'title' => 'Lap. Outlet AC',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_ec',
                            'title' => 'Lap. Outlet EC',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_ro',
                            'title' => 'Lap. Outlet RO',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_oss',
                            'title' => 'Lap. Outlet OSS',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_fiktif',
                            'title' => 'Lap. Outlet Fiktif',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_join_visit',
                            'title' => 'Lap. Join Visit',
                            'icon' => null,
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'User dan Karyawan',
            'icon' => 'users',
            'items' => [
                [
                    'title' => 'Users',
                    'icon' => 'users',
                    'id' => 'group_users',
                    'children' => [
                        [
                            'slug' => 'super_admin',
                            'title' => 'Super Admin',
                            'icon' => 'users',
                        ],
                        [
                            'slug' => 'user_koordinator',
                            'title' => 'User Koordinator',
                            'icon' => 'users',
                        ],
                        [
                            'slug' => 'user_sales',
                            'title' => 'User Sales',
                            'icon' => 'users',
                        ],
                        [
                            'slug' => 'user_lainnya',
                            'title' => 'User Lainnya',
                            'icon' => 'users',
                        ],
                    ],
                ],
                [
                    'slug' => 'role',
                    'title' => 'Role & Hak Akses',
                    'icon' => 'shield-user',
                ],
                [
                    'title' => 'Karyawan',
                    'icon' => 'users',
                    'id' => 'group_karyawan',
                    'children' => [
                        [
                            'slug' => 'karyawan_supervisor',
                            'title' => 'Karyawan Supervisor',
                            'icon' => 'id-card',
                        ],
                        [
                            'slug' => 'karyawan_koordinator',
                            'title' => 'Karyawan Koordinator',
                            'icon' => 'id-card',
                        ],
                        [
                            'slug' => 'karyawan_sales',
                            'title' => 'Karyawan Sales',
                            'icon' => 'id-card',
                        ],
                        [
                            'slug' => 'karyawan_lainnya',
                            'title' => 'Karyawan Lainnya',
                            'icon' => 'id-card',
                        ],
                        [
                            'slug' => 'kategori_surat_peringatan',
                            'title' => 'Kategori Surat Peringatan',
                            'icon' => 'tags',
                        ],
                        [
                            'slug' => 'surat_peringatan',
                            'title' => 'Surat Peringatan',
                            'icon' => 'file-text',
                        ],
                    ],
                ],
                [
                    'slug' => 'login_session',
                    'title' => 'Sesi Login',
                    'icon' => 'key',
                ],
                [
                    'title' => 'Laporan',
                    'icon' => 'book',
                    'id' => 'laptuser',
                    'children' => [
                        [
                            'slug' => 'lap_karyawan',
                            'title' => 'Lap. Karyawan',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_user',
                            'title' => 'Lap. User',
                            'icon' => null,
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Master Data & Pengaturan',
            'icon' => 'settings',
            // Tidak ditampilkan di daftar scroll sidebar, tapi di popup menu user (footer sidebar).
            'posisi' => 'footer',
            'items' => [
                [
                    'slug' => 'panduan',
                    'title' => 'Panduan',
                    'icon' => 'info',
                ],
                [
                    'slug' => 'pengumuman',
                    'title' => 'Pengumuman',
                    'icon' => 'megaphone',
                ],
                [
                    'slug' => 'backup_data',
                    'title' => 'Backup Data',
                    'icon' => 'database',
                ],
                [
                    'title' => 'Manajemen Produk',
                    'icon' => 'boxes',
                    'id' => 'produk_group',
                    'children' => [
                        [
                            'slug' => 'produk',
                            'title' => 'Produk',
                            'icon' => 'barcode',
                        ],
                        [
                            'slug' => 'produk_kompetitor',
                            'title' => 'Produk Kompetitor',
                            'icon' => 'box',
                        ],
                        [
                            'slug' => 'kategori_produk',
                            'title' => 'Kategori Produk',
                            'icon' => 'tags',
                        ],
                        [
                            'slug' => 'merk_produk',
                            'title' => 'Brand',
                            'icon' => 'badge-check',
                        ],
                        [
                            'slug' => 'satuan_produk',
                            'title' => 'Satuan Produk',
                            'icon' => 'ruler',
                        ],
                    ],
                ],
                [
                    'title' => 'Konfigurasi',
                    'icon' => 'settings',
                    'id' => 'konfigurasi_group',
                    'children' => [
                        [
                            'slug' => 'master_area',
                            'title' => 'Master Area',
                            'icon' => 'map-pin',
                        ],
                        [
                            'slug' => 'master_depo',
                            'title' => 'Master Warehouse / Depo',
                            'icon' => 'building',
                        ],
                        [
                            'slug' => 'kuota_motoris',
                            'title' => 'Kuota Motoris',
                            'icon' => 'users',
                        ],
                        [
                            'slug' => 'wilayah_depo',
                            'title' => 'Wilayah Depo',
                            'icon' => 'signpost',
                        ],
                        [
                            'slug' => 'master_project',
                            'title' => 'Master Project',
                            'icon' => 'network',
                        ],
                        [
                            'slug' => 'holiday_dates',
                            'title' => 'Tanggal Libur',
                            'icon' => 'calendar',
                        ],
                        [
                            'slug' => 'waktu_aktif_app',
                            'title' => 'Hari Kerja Aktif',
                            'icon' => 'clock',
                        ],
                        [
                            'slug' => 'target_setting',
                            'title' => 'Setting Target',
                            'icon' => 'chart-bar',
                        ],
                    ],
                ],
                [
                    'title' => 'Master Data',
                    'icon' => 'book',
                    'id' => 'master_data_group',
                    'children' => [
                        [
                            'slug' => 'provinsi',
                            'title' => 'Provinsi',
                            'icon' => 'flag',
                        ],
                        [
                            'slug' => 'city_group',
                            'title' => 'City Group',
                            'icon' => 'layers',
                        ],
                        [
                            'slug' => 'kota_kabupaten',
                            'title' => 'Kota Kabupaten',
                            'icon' => 'flag',
                        ],
                        [
                            'slug' => 'kecamatan',
                            'title' => 'Kecamatan',
                            'icon' => 'flag',
                        ],
                        [
                            'slug' => 'kelurahan',
                            'title' => 'Kelurahan',
                            'icon' => 'flag',
                        ],
                        [
                            'slug' => 'kategori_user',
                            'title' => 'Kategori User',
                            'icon' => 'user',
                        ],
                        [
                            'slug' => 'kategori_karyawan',
                            'title' => 'Kategori Karyawan',
                            'icon' => 'id-card',
                        ],
                        [
                            'slug' => 'tipe_outlet',
                            'title' => 'Tipe Outlet',
                            'icon' => 'tags',
                        ],
                        [
                            'slug' => 'kategori_outlet',
                            'title' => 'Kategori Outlet',
                            'icon' => 'tags',
                        ],
                        [
                            'slug' => 'rumus_insentif_point_sales',
                            'title' => 'Rumus Insentif Poin Sales',
                            'icon' => 'calculator',
                        ],
                        [
                            'slug' => 'sales_point_conversion_per_warehouse',
                            'title' => 'Konversi Poin Per Depo',
                            'icon' => 'arrow-left-right',
                        ],
                        [
                            'slug' => 'rumus_insentif_sales',
                            'title' => 'Rumus Insentif Sales',
                            'icon' => 'calculator',
                        ],
                        [
                            'slug' => 'rumus_insentif_koordinator',
                            'title' => 'Rumus Insentif Koordinator',
                            'icon' => 'calculator',
                        ],
                    ],
                ],
            ],
        ],
    ],

    'pages' => [
        // Halaman sendiri (BackupDataController); entri ini untuk judul, breadcrumb dan hak menu.
        'backup_data' => [
            'title' => 'Backup Data',
            'tables' => [],
            'same_as' => null,
        ],
        'home' => [
            'title' => 'Beranda',
            'tables' => [],
            'same_as' => null,
        ],
        // Gabungan menu monitoring harian sales. Filter tanggal dan KPI diatur per tabel.
        // 'tabs' => true: hanya tabel grup tab aktif (query ?tab=) yang ditampilkan. Tiap grup: tab_key, tab_label, tables.
        'monitoring_harian_sales' => [
            'title' => 'Monitoring Harian Sales',
            'tabs' => true,
            'tables' => [
                [
                    'tab_key' => 'checkin_pagi',
                    'tab_label' => 'Sales Checkin Outlet Pagi',
                    'tables' => [
                        ['table' => 'checkin_pagi', 'date_filter_column' => 'tanggal_data'],
                    ],
                ],
                [
                    'tab_key' => 'sales_tidak_penjualan',
                    'tab_label' => 'Sales Tidak Penjualan',
                    'tables' => [
                        ['table' => 'sales_tidak_penjualan', 'date_filter_column' => null, 'kpi' => null],
                    ],
                ],
                [
                    'tab_key' => 'sales_tidak_absen',
                    'tab_label' => 'Sales Tidak Absen Pagi',
                    'tables' => [
                        ['table' => 'sales_tidak_absen', 'date_filter_column' => null, 'kpi' => null],
                    ],
                ],
            ],
            'same_as' => null,
        ],
        // Gabungan rekap pencapaian SKU per motoris & per depo. 'tabs' => true: hanya grup tab aktif (query ?tab=) yang ditampilkan.
        'rekap_pencapaian_sku' => [
            'title' => 'Rekap Pencapaian SKU',
            'tabs' => true,
            'tables' => [
                [
                    'tab_key' => 'motoris',
                    'tab_label' => 'Per Motoris',
                    'tables' => [
                        'rekap_pencapaian_sku_motoris_tableachieved',
                        'rekap_pencapaian_sku_motoris_tablenotachieved',
                    ],
                ],
                [
                    'tab_key' => 'depo',
                    'tab_label' => 'Per Depo',
                    'tables' => [
                        'rekap_pencapaian_depo_sku_tableachieved',
                        'rekap_pencapaian_depo_sku_tablenotachieved',
                    ],
                ],
            ],
            'same_as' => null,
        ],
        'summary_all_depo' => [
            'title' => 'Summary All Depo',
            'tables' => [],
            'same_as' => null,
        ],
        // Gabungan Motoris Coverage, Motoris Performance, Monitor Absensi Motoris. 'tabs' => true: hanya grup tab aktif (query ?tab=) yang ditampilkan.
        'motoris_report' => [
            'title' => 'Motoris Report',
            'tabs' => true,
            'tables' => [
                [
                    'tab_key' => 'coverage',
                    'tab_label' => 'Coverage',
                    'tables' => [
                        'motoris_coverage',
                    ],
                ],
                [
                    'tab_key' => 'performance',
                    'tab_label' => 'Performance',
                    'tables' => [
                        'motoris_performance',
                    ],
                ],
                [
                    'tab_key' => 'absensi',
                    'tab_label' => 'Absensi',
                    'tables' => [
                        'monitor_absensi_motoris',
                    ],
                ],
            ],
            'same_as' => null,
        ],
        'checkin_depo' => [
            'title' => 'Absensi Datang & Pulang Depo',
            'tables' => [
                'lap_checkin_checkout',
            ],
            'same_as' => 'lap_checkin_checkout',
        ],
        'template_barang_canvassing' => [
            'title' => 'Template Barang Canvassing',
            'tables' => [
                'template_barang_canvassing',
            ],
            'same_as' => null,
        ],
        'barang_canvassing' => [
            'title' => 'Daftar Muatan Motoris',
            // Satu hari saja, bawaan hari ini: tanggal muatan (trx_canvassing.tanggal) dipilih lewat datepicker.
            'tables' => [
                ['table' => 'barang_canvassing', 'date_filter_column' => 'c.tanggal'],
            ],
            'same_as' => null,
            // Tabel mengisi sisa tinggi layar dan header kolom tetap terlihat saat di-scroll (lihat DataTable.vue).
            'tinggi_penuh' => true,
            // Tinggi tabel dipatok setinggi sisa layar walau datanya sedikit atau kosong.
            'tinggi_tetap' => true,
        ],
        'pengembalian_barang' => [
            'title' => 'Pengembalian Barang',
            'tables' => [
                'lap_pengembalian_barang',
            ],
            'same_as' => 'lap_pengembalian_barang',
        ],
        'tracking_sales' => [
            'title' => 'Tracking Last Seen Sales',
            'tables' => [
                'tracking_last_seen_sales',
            ],
            'same_as' => null,
        ],
        'motoris_ditandai' => [
            'title' => 'Motoris Ditandai',
            'tables' => [
                'tanda_motoris_rekap',
            ],
            'same_as' => null,
        ],
        'tracking_koordinator' => [
            'title' => 'Tracking Last Seen Koordinator',
            'tables' => [
                'tracking_last_seen_koordinator',
            ],
            'same_as' => null,
        ],
        'tracking_koordinator_history_location' => [
            'title' => 'Tracking History Koordinator Location',
            'tables' => [],
            'same_as' => null,
        ],
        'lap_checkin_checkout' => [
            'title' => 'Lap. Checkin Checkout',
            'tables' => [
                'lap_checkin_checkout',
            ],
            'same_as' => null,
        ],
        'lap_barang_canvassing' => [
            'title' => 'Lap. Barang Canvassing',
            'tables' => [
                'lap_barang_canvassing',
            ],
            'same_as' => null,
        ],
        'lap_barang_habis_terjual' => [
            'title' => 'Lap. Barang Habis Terjual',
            'tables' => [
                'lap_barang_habis_terjual',
            ],
            'same_as' => null,
        ],
        'lap_pengembalian_barang' => [
            'title' => 'Lap. Pengembalian Barang',
            'tables' => [
                'lap_pengembalian_barang',
            ],
            'same_as' => null,
        ],
        'loading_barang' => [
            'title' => 'Loading Barang',
            'tables' => [
                'loading_barang',
            ],
            'same_as' => null,
            // Tabel mengisi sisa tinggi layar dan header kolom tetap terlihat saat di-scroll (lihat DataTable.vue).
            'tinggi_penuh' => true,
            // Kode Transaksi tetap menempel di kiri saat tabel digeser horizontal (lihat DataTable.vue).
            'sticky_columns' => ['kode_transaksi'],
        ],
        'retur_barang' => [
            'title' => 'Retur Barang',
            'tables' => [
                'retur_barang',
            ],
            'same_as' => null,
        ],
        'pembayaran_pembelian' => [
            'title' => 'Setoran',
            'tables' => [
                'pembayaran_pembelian',
            ],
            'same_as' => null,
        ],
        'setor_margin' => [
            'title' => 'Setor Margin',
            'tables' => [
                'setor_margin',
            ],
            'same_as' => null,
        ],
        'distributor' => [
            'title' => 'Distributor',
            'tables' => [
                'distributor',
            ],
            'same_as' => null,
        ],
        'lap_loading_barang' => [
            'title' => 'Lap. Loading Barang',
            'tables' => [
                'lap_loading_barang',
            ],
            'same_as' => null,
            // Tabel mengisi sisa tinggi layar dan header kolom tetap terlihat saat di-scroll (lihat DataTable.vue).
            'tinggi_penuh' => true,
            // Kolom yang tetap menempel di kiri saat tabel digeser horizontal (lihat DataTable.vue).
            'sticky_columns' => ['trans_code'],
        ],
        'lap_loading_barang_detail' => [
            'title' => 'Lap. Loading Barang Detail',
            'tables' => [
                'lap_loading_barang_detail',
            ],
            'same_as' => null,
        ],
        'lap_retur_barang' => [
            'title' => 'Lap. Retur Barang',
            'tables' => [
                'lap_retur_barang',
            ],
            'same_as' => null,
        ],
        'lap_retur_barang_detail' => [
            'title' => 'Lap. Retur Barang Detail',
            'tables' => [
                'lap_retur_barang_detail',
            ],
            'same_as' => null,
        ],
        'lap_pembayaran_tagihan' => [
            'title' => 'Lap. Setoran',
            'tables' => [
                'lap_pembayaran_tagihan',
            ],
            'same_as' => null,
        ],
        'lap_setor_margin' => [
            'title' => 'Lap. Setor Margin',
            'tables' => [
                'lap_setor_margin',
            ],
            'same_as' => null,
        ],
        'checkin_outlet' => [
            'title' => 'Checkin Outlet',
            'tables' => [
                'lap_checkin_outlet',
            ],
            'same_as' => 'lap_checkin_outlet',
        ],
        'penjualan_barang' => [
            'title' => 'Penjualan Barang',
            'tables' => [
                'lap_penjualan_barang',
            ],
            'same_as' => 'lap_penjualan_barang',
        ],
        'stok_outlet' => [
            'title' => 'Stok Outlet',
            'tables' => [
                'lap_stok_outlet',
            ],
            'same_as' => 'lap_stok_outlet',
        ],
        'stok_kompetitor' => [
            'title' => 'Stok Kompetitor',
            'tables' => [
                'lap_stok_kompetitor',
            ],
            'same_as' => 'lap_stok_kompetitor',
        ],
        'lap_checkin_outlet' => [
            'title' => 'Lap. Checkin Outlet',
            'tables' => [
                'lap_checkin_outlet',
            ],
            'same_as' => null,
        ],
        'lap_effective_call' => [
            'title' => 'Lap. Efective Call',
            'tables' => [
                'lap_effective_call',
            ],
            'same_as' => null,
        ],
        'lap_effective_call_barang' => [
            'title' => 'Lap. Efective Call Detail',
            'tables' => [
                'lap_effective_call_barang',
            ],
            'same_as' => null,
        ],
        'lap_penjualan_barang' => [
            'title' => 'Lap. Penjualan Barang',
            'tables' => [
                'lap_penjualan_barang',
            ],
            'same_as' => null,
        ],
        'lap_penjualan_barang_detail' => [
            'title' => 'Lap. Penjualan Barang Detail',
            'tables' => [
                'lap_penjualan_barang_detail',
            ],
            'same_as' => null,
        ],
        'lap_penjualan_barang_terbaik_harian' => [
            'title' => 'Lap. Barang Terbaik Harian',
            'tables' => [
                ['table' => 'lap_penjualan_barang_terbaik_harian', 'date_filter_column' => 'tanggal_data'],
            ],
            'same_as' => null,
        ],
        'lap_penjualan_barang_terbaik_mingguan' => [
            'title' => 'Lap. Barang Terbaik Mingguan',
            'tables' => [
                ['table' => 'lap_penjualan_barang_terbaik_mingguan', 'date_filter_column' => 'tanggal_data'],
            ],
            'same_as' => null,
        ],
        'lap_penjualan_barang_terbaik_bulanan' => [
            'title' => 'Lap. Barang Terbaik Bulanan',
            'tables' => [
                ['table' => 'lap_penjualan_barang_terbaik_bulanan', 'date_filter_column' => 'tanggal_data'],
            ],
            'same_as' => null,
        ],
        'lap_stok_outlet' => [
            'title' => 'Lap. Stok Outlet',
            'tables' => [
                'lap_stok_outlet',
            ],
            'same_as' => null,
        ],
        'lap_stok_kompetitor' => [
            'title' => 'Lap. Stok Kompetitor',
            'tables' => [
                'lap_stok_kompetitor',
            ],
            'same_as' => null,
        ],
        'absensi_sales' => [
            'title' => 'Absensi Sales',
            'tables' => [
                'lap_absensi_sales',
            ],
            'same_as' => 'lap_absensi_sales',
        ],
        'absensi_sales_terlambat' => [
            'title' => 'Absensi Sales Terlambat',
            'tables' => [
                'absensi_sales_terlambat',
            ],
            'same_as' => null,
        ],
        'absensi_koordinator' => [
            'title' => 'Absensi Koordinator',
            'tables' => [
                'absensi_koordinator',
            ],
            'same_as' => null,
        ],
        'absensi_ssk_supervisor' => [
            'title' => 'Absensi SSK / Supervisor',
            'tables' => [
                'absensi_ssk_supervisor',
            ],
            'same_as' => null,
        ],
        'permintaan_izin' => [
            'title' => 'Permintaan Izin',
            'tables' => [
                'permintaan_izin',
            ],
            'same_as' => null,
        ],
        'parameter_pemotong_gaji' => [
            'title' => 'Parameter Pemotong Gaji',
            'tables' => [
                'parameter_pemotong_gaji',
            ],
            'same_as' => null,
        ],
        'potongan_gaji' => [
            'title' => 'Potongan Gaji',
            'tables' => [
                'potongan_gaji',
            ],
            'same_as' => null,
        ],
        'penggajian_sales' => [
            'title' => 'Penggajian Sales',
            'tables' => [
                'penggajian_sales',
            ],
            'same_as' => null,
        ],
        'penggajian_koordinator' => [
            'title' => 'Penggajian Koordinator',
            'tables' => [
                'penggajian_koordinator',
            ],
            'same_as' => null,
        ],
        'lap_absensi_sales' => [
            'title' => 'Lap. Absensi Sales',
            'tables' => [
                'lap_absensi_sales',
            ],
            'same_as' => null,
        ],
        'lap_absensi_koordinator' => [
            'title' => 'Lap. Absensi Koordinator',
            'tables' => [
                'lap_absensi_koordinator',
            ],
            'same_as' => null,
        ],
        'lap_izin_sakit' => [
            'title' => 'Lap. Izin/Sakit',
            'tables' => [
                'lap_izin_sakit',
            ],
            'same_as' => null,
        ],
        'lap_insentif_sales' => [
            'title' => 'Lap. Insentif Sales',
            'tables' => [
                ['table' => 'lap_insentif_sales', 'date_filter_column' => 'periode'],
            ],
            'same_as' => null,
        ],
        'lap_insentif_koordinator' => [
            'title' => 'Lap. Insentif Koordinator',
            'tables' => [
                'lap_insentif_koordinator',
            ],
            'same_as' => null,
        ],
        'lap_penggajian_sales' => [
            'title' => 'Lap. Penggajian Sales',
            'tables' => [
                'lap_penggajian_sales',
            ],
            'same_as' => null,
        ],
        'lap_penggajian_koordinator' => [
            'title' => 'Lap. Penggajian Koordinator',
            'tables' => [
                'lap_penggajian_koordinator',
            ],
            'same_as' => null,
        ],
        'outlet_noo' => [
            'title' => 'New Open Outlet',
            // Seperti Marketama: outlet yang didaftarkan pada tanggal terpilih (bawaan hari ini). Lap. Outlet NOO = semua tanggal.
            'tables' => [
                ['table' => 'lap_outlet_noo', 'date_filter_column' => 'b.tanggal'],
            ],
            'same_as' => 'lap_outlet_noo',
        ],
        // Sama dengan API mobile sales motoris: master mk_outlet, plus outlet baru dari HP (trx_outlet_baru).
        // Data laporan lama tetap ada di menu Lap. Outlet All (lap_outlet_all).
        'outlet_all' => [
            'title' => 'All Outlet',
            'tabs' => true,
            'tables' => [
                [
                    'tab_key' => 'master',
                    'tab_label' => 'Master Outlet',
                    'tables' => ['mk_outlet'],
                ],
                [
                    'tab_key' => 'baru',
                    'tab_label' => 'Outlet Baru dari HP',
                    'tables' => ['trx_outlet_baru'],
                ],
            ],
            'same_as' => null,
        ],
        'outlet_ac' => [
            'title' => 'Actual Call',
            'tables' => [
                'lap_outlet_ac',
            ],
            'same_as' => 'lap_outlet_ac',
        ],
        'outlet_ec' => [
            'title' => 'Effective Call',
            'tables' => [
                'lap_outlet_ec',
            ],
            'same_as' => 'lap_outlet_ec',
        ],
        'outlet_ro' => [
            'title' => 'Repeat Order',
            'tables' => [
                'lap_outlet_ro',
            ],
            'same_as' => 'lap_outlet_ro',
        ],
        'outlet_oss' => [
            'title' => 'Out of Stock',
            'tables' => [
                'lap_outlet_oss',
            ],
            'same_as' => 'lap_outlet_oss',
        ],
        'outlet_duplikat' => [
            'title' => 'Outlet Duplikat / Fiktif',
            'tables' => [
                'outlet_duplikat',
            ],
            'same_as' => null,
        ],
        // Join visit dari aplikasi HP koordinator + riwayat aplikasi lama yang dipindah (trx_join_visit).
        // Data laporan lama apa adanya tetap di Lap. Join Visit (lap_join_visit).
        'join_visit' => [
            'title' => 'Join Visit',
            'tables' => [
                'trx_join_visit',
            ],
            'same_as' => null,
        ],
        'lap_outlet_noo' => [
            'title' => 'Lap. Outlet NOO',
            'tables' => [
                'lap_outlet_noo',
            ],
            'same_as' => null,
        ],
        'lap_outlet_all' => [
            'title' => 'Lap. Outlet All',
            'tables' => [
                'lap_outlet_all',
            ],
            'same_as' => null,
        ],
        'lap_outlet_ac' => [
            'title' => 'Lap. Outlet AC',
            'tables' => [
                'lap_outlet_ac',
            ],
            'same_as' => null,
        ],
        'lap_outlet_ec' => [
            'title' => 'Lap. Outlet EC',
            'tables' => [
                'lap_outlet_ec',
            ],
            'same_as' => null,
        ],
        'lap_outlet_ro' => [
            'title' => 'Lap. Outlet RO',
            'tables' => [
                'lap_outlet_ro',
            ],
            'same_as' => null,
        ],
        'lap_outlet_oss' => [
            'title' => 'Lap. Outlet OSS',
            'tables' => [
                'lap_outlet_oss',
            ],
            'same_as' => null,
        ],
        'lap_outlet_fiktif' => [
            'title' => 'Lap. Outlet Fiktif',
            'tables' => [
                'lap_outlet_fiktif',
            ],
            'same_as' => null,
        ],
        'lap_join_visit' => [
            'title' => 'Lap. Join Visit',
            'tables' => [
                // Sama dengan menu Join Visit: data HP koordinator + riwayat lama hasil migrasi.
                'trx_join_visit',
            ],
            'same_as' => null,
        ],
        'super_admin' => [
            'title' => 'Super Admin',
            'tables' => [
                'super_admin',
            ],
            'same_as' => null,
        ],
        'user_koordinator' => [
            'title' => 'User Koordinator',
            'tables' => [
                'user_koordinator',
            ],
            'same_as' => null,
        ],
        'user_sales' => [
            'title' => 'User Sales',
            'tables' => [
                'user_sales',
            ],
            'same_as' => null,
        ],
        'user_lainnya' => [
            'title' => 'User Lainnya',
            'tables' => [
                'user_lainnya',
            ],
            'same_as' => null,
        ],
        'karyawan_supervisor' => [
            'title' => 'Karyawan Supervisor',
            'tables' => [
                'karyawan_supervisor',
            ],
            'same_as' => null,
        ],
        'karyawan_koordinator' => [
            'title' => 'Karyawan Koordinator',
            'tables' => [
                'karyawan_koordinator',
            ],
            'same_as' => null,
        ],
        'karyawan_sales' => [
            'title' => 'Karyawan Sales',
            'tables' => [
                'karyawan_sales',
            ],
            'same_as' => null,
        ],
        'karyawan_lainnya' => [
            'title' => 'Karyawan Lainnya',
            'tables' => [
                'karyawan_lainnya',
            ],
            'same_as' => null,
        ],
        'kategori_surat_peringatan' => [
            'title' => 'Kategori Surat Peringatan',
            'tables' => [
                'kategori_surat_peringatan',
            ],
            'same_as' => null,
        ],
        'surat_peringatan' => [
            'title' => 'Surat Peringatan',
            'tables' => [
                'surat_peringatan',
            ],
            'same_as' => null,
        ],
        'login_session' => [
            'title' => 'Sesi Login',
            'tables' => [
                'login_session',
            ],
            'same_as' => null,
        ],
        'lap_karyawan' => [
            'title' => 'Lap. Karyawan',
            'tables' => [
                'lap_karyawan',
            ],
            'same_as' => null,
        ],
        'lap_user' => [
            'title' => 'Lap. User',
            'tables' => [
                'lap_user',
            ],
            'same_as' => null,
        ],
        'panduan' => [
            'title' => 'Panduan',
            'tables' => [
                'panduan',
            ],
            'same_as' => null,
        ],
        'pengumuman' => [
            'title' => 'Pengumuman',
            'tables' => [
                'pengumuman',
            ],
            'same_as' => null,
        ],
        'produk' => [
            'title' => 'Produk',
            'tables' => [
                'produk',
            ],
            'same_as' => null,
        ],
        'produk_kompetitor' => [
            'title' => 'Produk Kompetitor',
            'tables' => [
                'produk_kompetitor',
            ],
            'same_as' => null,
        ],
        'kategori_produk' => [
            'title' => 'Kategori Produk',
            'tables' => [
                'kategori_produk',
            ],
            'same_as' => null,
        ],
        'merk_produk' => [
            'title' => 'Brand',
            'tables' => [
                'merk_produk',
            ],
            'same_as' => null,
        ],
        'satuan_produk' => [
            'title' => 'Satuan Produk',
            'tables' => [
                'satuan_produk',
            ],
            'same_as' => null,
        ],
        'master_area' => [
            'title' => 'Master Area',
            'tables' => [
                'master_area',
            ],
            'same_as' => null,
            // Tabel mengisi sisa tinggi layar dan header kolom tetap terlihat saat di-scroll (lihat DataTable.vue).
            'tinggi_penuh' => true,
            // Kode dan Nama tetap menempel di kiri saat tabel digeser horizontal (lihat DataTable.vue).
            'sticky_columns' => ['kode', 'nama'],
        ],
        // Halaman sendiri (KuotaMotorisController): kuota sales motoris per depo isian admin, untuk Motoris Report.
        'kuota_motoris' => [
            'title' => 'Kuota Motoris',
            'tables' => [],
            'same_as' => null,
        ],
        'master_depo' => [
            'title' => 'Master Warehouse / Depo',
            'tables' => [
                'master_depo',
            ],
            'same_as' => null,
            // Tabel mengisi sisa tinggi layar dan header kolom tetap terlihat saat di-scroll (lihat DataTable.vue).
            'tinggi_penuh' => true,
            // Kolom yang tetap menempel di kiri saat tabel digeser horizontal (lihat DataTable.vue).
            'sticky_columns' => ['nama'],
        ],
        'wilayah_depo' => [
            'title' => 'Wilayah Depo',
            'tables' => [
                'wilayah_depo',
            ],
            'same_as' => null,
        ],
        'master_project' => [
            'title' => 'Master Project',
            'tables' => [
                'master_project',
            ],
            'same_as' => null,
        ],
        'holiday_dates' => [
            'title' => 'Tanggal Libur',
            'tables' => [
                'holiday_dates',
            ],
            'same_as' => null,
        ],
        'waktu_aktif_app' => [
            'title' => 'Hari Kerja Aktif',
            'tables' => [
                'waktu_aktif_app',
            ],
            'same_as' => null,
        ],
        'target_setting' => [
            'title' => 'Setting Target',
            'tables' => [
                'target_setting',
            ],
            'same_as' => null,
        ],
        'provinsi' => [
            'title' => 'Provinsi',
            'tables' => [
                'provinsi',
            ],
            'same_as' => null,
        ],
        'city_group' => [
            'title' => 'City Group',
            'tables' => [
                'city_group',
            ],
            'same_as' => null,
        ],
        'kota_kabupaten' => [
            'title' => 'Kota Kabupaten',
            'tables' => [
                'kota_kabupaten',
            ],
            'same_as' => null,
        ],
        'kecamatan' => [
            'title' => 'Kecamatan',
            'tables' => [
                'kecamatan',
            ],
            'same_as' => null,
        ],
        'kelurahan' => [
            'title' => 'Kelurahan',
            'tables' => [
                'kelurahan',
            ],
            'same_as' => null,
        ],
        'kategori_user' => [
            'title' => 'Kategori User',
            'tables' => [
                'kategori_user',
            ],
            'same_as' => null,
        ],
        'kategori_karyawan' => [
            'title' => 'Kategori Karyawan',
            'tables' => [
                'kategori_karyawan',
            ],
            'same_as' => null,
        ],
        'tipe_outlet' => [
            'title' => 'Tipe Outlet',
            'tables' => [
                'tipe_outlet',
            ],
            'same_as' => null,
        ],
        'kategori_outlet' => [
            'title' => 'Kategori Outlet',
            'tables' => [
                'kategori_outlet',
            ],
            'same_as' => null,
        ],
        'rumus_insentif_point_sales' => [
            'title' => 'Rumus Insentif Poin Sales',
            'tables' => [
                'rumus_insentif_point_sales_0',
                'rumus_insentif_point_sales_1',
            ],
            'same_as' => null,
        ],
        'sales_point_conversion_per_warehouse' => [
            'title' => 'Konversi Poin Per Depo',
            'tables' => [
                'sales_point_conversion_per_warehouse',
            ],
            'same_as' => null,
        ],
        'rumus_insentif_sales' => [
            'title' => 'Rumus Insentif Sales',
            'tables' => [
                'rumus_insentif_sales',
            ],
            'same_as' => null,
        ],
        'rumus_insentif_koordinator' => [
            'title' => 'Rumus Insentif Koordinator',
            'tables' => [
                'rumus_insentif_koordinator',
            ],
            'same_as' => null,
        ],
    ],

    'tables' => [
        'tracking_last_seen_sales' => [
            'title' => 'Tracking Last Seen Sales',
            'sumber' => SumberLastSeenSales::class,
            'columns' => [
                'nama' => 'Nama',
                'telepon' => 'Telepon',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'terakhir_terlihat' => 'Terakhir Terlihat',
                'lokasi' => 'Lokasi',
                'riwayat' => 'Riwayat',
                'ditandai' => 'Ditandai',
                'lokasi_palsu' => 'Lokasi Palsu',
            ],
        ],
        'tracking_last_seen_koordinator' => [
            'title' => 'Tracking Last Seen Koordinator',
            'sumber' => SumberLastSeenKoordinator::class,
            'columns' => [
                'nama' => 'Nama',
                'telepon' => 'Telepon',
                'depo' => 'Depo',
                'terakhir_terlihat' => 'Terakhir Terlihat',
                'lokasi' => 'Lokasi',
                'riwayat' => 'Riwayat',
                'lokasi_palsu' => 'Lokasi Palsu',
            ],
        ],
        'tanda_motoris_rekap' => [
            'title' => 'Motoris Ditandai',
            'sumber' => SumberTandaMotoris::class,
            'columns' => [
                'tanggal' => 'Tanggal',
                'motoris' => 'Motoris',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'kategori' => 'Kategori',
                'catatan' => 'Catatan',
                'ditandai_oleh' => 'Ditandai Oleh',
                'ditandai_pada' => 'Ditandai Pada',
                'status' => 'Status',
                'riwayat' => 'Riwayat',
            ],
        ],
        'pembayaran_pembelian' => [
            'title' => 'Setoran',
            'columns' => [
                'no_purchase' => 'No. Purchase',
                'tanggal_setoran' => 'Tanggal Setoran',
                'koordinator' => 'Koordinator',
                'distributor' => 'Distributor',
                'diterima_oleh' => 'Diterima Oleh',
                'nominal' => 'Nominal',
                'lampiran' => 'Lampiran',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'setor_margin' => [
            'title' => 'Setor Margin',
            'columns' => [
                'koordinator' => 'Koordinator',
                'tanggal_setoran' => 'Tanggal Setoran',
                'diterima_oleh' => 'Diterima Oleh',
                'nominal' => 'Nominal',
                'lampiran' => 'Lampiran',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_pembayaran_tagihan' => [
            'title' => 'Lap. Setoran',
            'columns' => [
                'kode_loading' => 'Kode Loading',
                'tanggal_bayar' => 'Tanggal Bayar',
                'depo' => 'Depo',
                'distributor' => 'Distributor',
                'koordinator' => 'Koordinator',
                'diterima_oleh' => 'Diterima Oleh',
                'nominal' => 'Nominal',
                'lampiran' => 'Lampiran',
                'dibuat_oleh' => 'Dibuat Oleh',
                'dibuat_pada' => 'Dibuat Pada',
            ],
        ],
        'lap_setor_margin' => [
            'title' => 'Lap. Setor Margin',
            'columns' => [
                'tanggal_bayar' => 'Tanggal Bayar',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'diterima_oleh' => 'Diterima Oleh',
                'nominal' => 'Nominal',
                'lampiran' => 'Lampiran',
                'dibuat_oleh' => 'Dibuat Oleh',
                'dibuat_pada' => 'Dibuat Pada',
            ],
        ],
        'potongan_gaji' => [
            'title' => 'Potongan Gaji',
            'columns' => [
                'nama_karyawan' => 'Nama Karyawan',
                'koordinator' => 'Koordinator',
                'depo' => 'Depo',
                'tanggal' => 'Tanggal',
                'nominal' => 'Nominal',
                'keterangan' => 'Keterangan',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'penggajian_sales' => [
            'title' => 'Penggajian Sales',
            'columns' => [
                'no_penggajian' => 'No. Penggajian',
                'nama_penggajian' => 'Nama Penggajian',
                'koordinator' => 'Koordinator',
                'depo' => 'Depo',
                'periode' => 'Periode',
                'jml_karyawan' => 'Jml Karyawan',
                'total_gaji' => 'Total Gaji',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'penggajian_koordinator' => [
            'title' => 'Penggajian Koordinator',
            'columns' => [
                'no_penggajian' => 'No. Penggajian',
                'nama_penggajian' => 'Nama Penggajian',
                'koordinator' => 'Koordinator',
                'depo' => 'Depo',
                'periode' => 'Periode',
                'hari_kerja' => 'Hari Kerja',
                'total_gaji' => 'Total Gaji',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_insentif_sales' => [
            'title' => 'Lap. Insentif Sales',
            'sumber' => SumberInsentifSales::class,
            'columns' => [
                'no_payroll' => 'No. Payroll',
                'periode' => 'Periode',
                'nama_sales' => 'Nama Sales',
                'telepon' => 'Telepon',
                'koordinator' => 'Koordinator',
                'depo' => 'Depo',
                'insentif_ec' => 'Insentif EC',
                'total_ec' => 'Total EC',
                'total_poin' => 'Total Poin',
                'insentif_poin' => 'Nilai Poin',
                'insentif_absensi' => 'Insentif Absensi',
                'total_insentif' => 'Total Insentif',
            ],
        ],
        'lap_insentif_koordinator' => [
            'title' => 'Lap. Insentif Koordinator',
            'columns' => [
                'no_payroll' => 'No. Payroll',
                'periode' => 'Periode',
                'nama_koordinator' => 'Nama Koordinator',
                'telepon' => 'Telepon',
                'depo' => 'Depo',
                'insentif_ec' => 'Insentif EC',
                'insentif_absensi' => 'Insentif Absensi',
                'total_insentif' => 'Total Insentif',
            ],
        ],
        'lap_penggajian_sales' => [
            'title' => 'Lap. Penggajian Sales',
            'columns' => [
                'no_payroll' => 'No. Payroll',
                'periode' => 'Periode',
                'nama_sales' => 'Nama Sales',
                'koordinator' => 'Koordinator',
                'depo' => 'Depo',
                'gaji_pokok' => 'Gaji Pokok',
                'hari_kerja' => 'Hari Kerja',
                'hari_izin' => 'Hari Izin',
                'tunj_bbm_makan' => 'Tunj. BBM & Makan',
                'sewa_motor' => 'Sewa Motor',
                'tunj_internet' => 'Tunj. Internet',
                'tunj_jabatan' => 'Tunj. Jabatan',
                'potongan' => 'Potongan',
                'insentif_ec' => 'Insentif EC',
                'insentif_absensi' => 'Insentif Absensi',
                'gaji_bersih' => 'Gaji Bersih',
            ],
        ],
        'lap_penggajian_koordinator' => [
            'title' => 'Lap. Penggajian Koordinator',
            'columns' => [
                'no_payroll' => 'No. Payroll',
                'periode' => 'Periode',
                'nama_koordinator' => 'Nama Koordinator',
                'depo' => 'Depo',
                'area' => 'Area',
                'gaji_pokok' => 'Gaji Pokok',
                'hari_kerja' => 'Hari Kerja',
                'hari_izin' => 'Hari Izin',
                'tunj_bbm_makan' => 'Tunj. BBM & Makan',
                'sewa_motor' => 'Sewa Motor',
                'tunj_internet' => 'Tunj. Internet',
                'tunj_jabatan' => 'Tunj. Jabatan',
                'potongan' => 'Potongan',
                'insentif_ec' => 'Insentif EC',
                'insentif_absensi' => 'Insentif Absensi',
                'gaji_bersih' => 'Gaji Bersih',
            ],
        ],
        'lap_outlet_ro' => [
            'title' => 'Lap. Outlet RO',
            'sumber' => SumberOutletRo::class,
            'columns' => [
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'kode_outlet' => 'Kode Outlet',
                'nama_outlet' => 'Nama Outlet',
                'contact_person' => 'Contact Person',
                'telepon' => 'Telepon',
                'alamat' => 'Alamat',
                'area' => 'Area',
                'provinsi' => 'Provinsi',
                'kota_kab' => 'Kota/Kab',
                'kecamatan' => 'Kecamatan',
                'kelurahan' => 'Kelurahan',
                'latitude' => 'Latitude',
                'longitude' => 'Longitude',
                'dibuat_oleh' => 'Dibuat Oleh',
                'dibuat_pada' => 'Dibuat Pada',
            ],
        ],
        'kategori_surat_peringatan' => [
            'title' => 'Kategori Surat Peringatan',
            'columns' => [
                'nama_kategori' => 'Nama Kategori',
                'total_sp' => 'Total SP',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'surat_peringatan' => [
            'title' => 'Surat Peringatan',
            'columns' => [
                'karyawan' => 'Karyawan',
                'kategori_sp' => 'Kategori SP',
                'tanggal' => 'Tanggal',
                'keterangan' => 'Keterangan',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'produk_kompetitor' => [
            'title' => 'Produk Kompetitor',
            'columns' => [
                'nama_produk' => 'Nama Produk',
                'satuan' => 'Satuan',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'kategori_produk' => [
            'title' => 'Kategori Produk',
            'columns' => [
                'kode' => 'Kode',
                'nama' => 'Nama',
                'total_produk' => 'Total Produk',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'karyawan_supervisor' => [
            'title' => 'Karyawan Supervisor',
            'columns' => [
                'nama_lengkap' => 'Nama Lengkap',
                'no_hp' => 'No HP',
                'email' => 'Email',
                'status' => 'Status',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'master_project' => [
            'title' => 'Master Project',
            'columns' => [
                'no' => 'No',
                'kode' => 'Kode',
                'nama_project' => 'Nama Project',
                'deskripsi' => 'Deskripsi',
                'total_depo' => 'Total Depo',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'tipe_outlet' => [
            'title' => 'Tipe Outlet',
            'columns' => [
                'source_id' => 'ID',
                'kode' => 'Kode',
                'tipe_outlet' => 'Tipe Outlet',
            ],
        ],
        'super_admin' => [
            'title' => 'Super Admin',
            'columns' => [
                'username' => 'Username',
                'nickname' => 'Nickname',
                'karyawan' => 'Karyawan',
                'status' => 'Status',
                'keterangan' => 'Keterangan',
                'login_terakhir_pada' => 'Login Terakhir',
            ],
        ],
        'satuan_produk' => [
            'title' => 'Satuan Produk',
            'columns' => [
                'nama' => 'Nama',
                'total_produk' => 'Total Produk',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'karyawan_lainnya' => [
            'title' => 'Karyawan Lainnya',
            'columns' => [
                'nama_lengkap' => 'Nama Lengkap',
                'kategori' => 'Kategori',
                'no_hp' => 'No HP',
                'email' => 'Email',
                'status' => 'Status',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'user_lainnya' => [
            'title' => 'User Lainnya',
            'columns' => [
                'username' => 'Username',
                'nickname' => 'Nickname',
                'kategori_user' => 'Kategori',
                'karyawan' => 'Karyawan',
                'depo' => 'Depo',
                'status' => 'Status',
                'keterangan' => 'Keterangan',
                'login_terakhir_pada' => 'Login Terakhir',
            ],
        ],
        'pengumuman' => [
            'title' => 'Pengumuman',
            'columns' => [
                'pesan' => 'Pesan',
                'kategori_user' => 'Kategori User',
                'total_user' => 'Total User',
                'tanggal_notifikasi' => 'Tanggal Notifikasi',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'merk_produk' => [
            'title' => 'Brand',
            'columns' => [
                'kode' => 'Kode',
                'nama' => 'Nama',
                'total_produk' => 'Total Produk',
                'status_aktif' => 'Status Aktif',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'parameter_pemotong_gaji' => [
            'title' => 'Parameter Pemotong Gaji',
            'columns' => [
                'nama' => 'Nama',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'city_group' => [
            'title' => 'City Group',
            'columns' => [
                'source_id' => 'ID',
                'nama_city_group' => 'Nama City Group',
            ],
        ],
        'lap_penjualan_barang_terbaik_harian' => [
            'title' => 'Lap. Barang Terbaik Harian',
            'sumber' => SumberBarangTerbaikHarian::class,
            'columns' => [
                'tanggal_data' => 'Tanggal Data',
                'product_id' => 'Product ID',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'total_qty' => 'Total Qty',
                'total_amount' => 'Total Amount',
                'total_discount' => 'Total Discount',
                'total_transaksi' => 'Total Transaksi',
            ],
        ],
        'lap_penjualan_barang_terbaik_mingguan' => [
            'title' => 'Lap. Barang Terbaik Mingguan',
            'sumber' => SumberBarangTerbaikMingguan::class,
            'columns' => [
                'product_id' => 'Product ID',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'total_qty' => 'Total Qty',
                'total_amount' => 'Total Amount',
                'total_discount' => 'Total Discount',
                'total_transaksi' => 'Total Transaksi',
            ],
        ],
        'holiday_dates' => [
            'title' => 'Tanggal Libur',
            'columns' => [
                'nama_libur' => 'Nama Libur',
                'tanggal' => 'Tanggal',
                'keterangan' => 'Keterangan',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_penjualan_barang_terbaik_bulanan' => [
            'title' => 'Lap. Barang Terbaik Bulanan',
            'sumber' => SumberBarangTerbaikBulanan::class,
            'columns' => [
                'product_id' => 'Product ID',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'total_qty' => 'Total Qty',
                'total_amount' => 'Total Amount',
                'total_discount' => 'Total Discount',
                'total_transaksi' => 'Total Transaksi',
            ],
        ],
        'produk' => [
            'title' => 'Produk',
            'columns' => [
                'kode' => 'Kode',
                'nama_produk' => 'Nama Produk',
                'merk' => 'Merk',
                'kategori' => 'Kategori',
                'satuan' => 'Satuan',
                'harga_beli' => 'Harga Beli',
                'harga_jual' => 'Harga Jual',
                'produk_fokus' => 'Produk Fokus',
                'drop_size' => 'Drop Size',
                'maksimal_poin_per_invoice' => 'Maksimal Poin Per Invoice',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'sales_point_conversion_per_warehouse' => [
            'title' => 'Konversi Poin Per Depo',
            'columns' => [
                'depo' => 'Depo',
                'poin_insentif_rp' => 'Poin Insentif (Rp)',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'master_depo' => [
            'title' => 'Master Warehouse / Depo',
            'columns' => [
                'nama' => 'Nama',
                'kode' => 'Kode',
                'projek' => 'Projek',
                'kota_besar' => 'Kota Besar',
                'minimum_sku' => 'Minimum SKU',
                'multi_polygon_sales' => 'Multi Polygon Sales',
                'provinsi' => 'Provinsi',
                'kota_kab' => 'Kota/Kab',
                'kecamatan' => 'Kecamatan',
                'kelurahan_desa' => 'Kelurahan/Desa',
                'alamat' => 'Alamat',
                'total_koordinator' => 'Total Koordinator',
                'jumlah_sales_aktif' => 'Jumlah Sales Aktif',
                'jumlah_wilayah_depo' => 'Jumlah Wilayah Depo',
                'kuota_koordinator' => 'Kuota Koordinator',
                'kuota_sales_motoris' => 'Kuota Sales Motoris',
                'target_call' => 'Target Call',
                'target_ec' => 'Target EC',
                'durasi_tracking_gps' => 'Durasi Tracking GPS',
                'insentif_per_poin' => 'Insentif per Poin',
                'outlet_1_sku' => 'Outlet 1 SKU',
                'minimal_value_transaksi' => 'Minimal Value Transaksi',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
                'status' => 'Status',
            ],
        ],
        'wilayah_depo' => [
            'title' => 'Wilayah Depo',
            'columns' => [
                'nama' => 'Nama',
                'depo_warehouse' => 'Depo / Warehouse',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_izin_sakit' => [
            'title' => 'Lap. Izin/Sakit',
            'columns' => [
                'source_id' => 'ID',
                'date_leave' => 'Date Leave',
                'status' => 'Status',
                'reason_for_absence' => 'Reason For Absence',
                'employee_name' => 'Employee Name',
                'employee_phone' => 'Employee Phone',
                'category_name' => 'Category Name',
                'koordinator_name' => 'Koordinator Name',
                'warehouse_name' => 'Warehouse Name',
                'status_label' => 'Status Label',
                'status_badge' => 'Status Badge',
            ],
        ],
        'distributor' => [
            'title' => 'Distributor',
            'columns' => [
                'kode' => 'Kode',
                'nama_distributor' => 'Nama Distributor',
                'depo' => 'Depo',
                'no_telepon' => 'No Telepon',
                'contact_person' => 'Contact Person',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'provinsi' => [
            'title' => 'Provinsi',
            'columns' => [
                'source_id' => 'ID',
                'kode' => 'Kode',
                'nama_provinsi' => 'Nama Provinsi',
            ],
        ],
        'sales_tidak_absen' => [
            'title' => 'Sales Tidak Absen Pagi',
            'columns' => [
                'tanggal_data' => 'Tanggal Data',
                'no' => 'No',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'nama_sales' => 'Nama Sales',
            ],
        ],
        'sales_tidak_penjualan' => [
            'title' => 'Sales Tidak Penjualan',
            'columns' => [
                'tanggal_data' => 'Tanggal Data',
                'no' => 'No',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'nama_sales' => 'Nama Sales',
                'jumlah_visit_outlet' => 'Jumlah Visit Outlet',
            ],
        ],
        'absensi_ssk_supervisor' => [
            'title' => 'Absensi SSK / Supervisor',
            'columns' => [
                'nama_karyawan' => 'Nama Karyawan',
                'depo' => 'Depo',
                'tanggal' => 'Tanggal',
                'jam_masuk' => 'Jam Masuk',
                'jam_keluar' => 'Jam Keluar',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'user_koordinator' => [
            'title' => 'User Koordinator',
            'columns' => [
                'username' => 'Username',
                'nickname' => 'Nickname',
                'karyawan' => 'Karyawan',
                'depo' => 'Depo',
                'status' => 'Status',
                'keterangan' => 'Keterangan',
                'login_terakhir_pada' => 'Login Terakhir',
            ],
        ],
        'karyawan_koordinator' => [
            'title' => 'Karyawan Koordinator',
            'columns' => [
                'nama_lengkap' => 'Nama Lengkap',
                'depo' => 'Depo',
                'wilayah_depo' => 'Wilayah Depo',
                'jumlah_sales' => 'Jumlah Sales',
                'no_hp' => 'No HP',
                'status' => 'Status',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'checkin_pagi' => [
            'title' => 'Sales Checkin Outlet Pagi',
            // Dihitung dari trx_kunjungan + mk_karyawan untuk tanggal terpilih (tabel checkin_pagi = salinan lama Marketama).
            'sumber' => SumberCheckinPagi::class,
            'columns' => [
                'no' => 'No',
                'nama_sales' => 'Nama Sales',
                'koordinator' => 'Koordinator',
                'depo' => 'Depo',
                'checkin_pertama_hari_ini' => 'Checkin Pertama Hari Ini',
            ],
        ],
        'permintaan_izin' => [
            'title' => 'Permintaan Izin',
            'columns' => [
                'karyawan' => 'Karyawan',
                'koordinator' => 'Koordinator',
                'tanggal_izin' => 'Tanggal Izin',
                'status' => 'Status',
                'alasan' => 'Alasan',
                'depo' => 'Depo',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_retur_barang' => [
            'title' => 'Lap. Retur Barang',
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'purchase_trans_code' => 'Purchase Trans Code',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'distributor_name' => 'Distributor Name',
                'koordinator_name' => 'Koordinator Name',
                'warehouse_name' => 'Warehouse Name',
                'total_item' => 'Total Item',
                'total_qty' => 'Total Qty',
                'grand_total' => 'Grand Total',
                'total_discount' => 'Total Discount',
                'updated_by_name' => 'Updated By Name',
                'updated_at' => 'Updated At',
            ],
        ],
        'lap_loading_barang' => [
            'title' => 'Lap. Loading Barang',
            'columns' => [
                'trans_code' => 'Trans Code',
                'datetime_trans' => 'Datetime Trans',
                'distributor_name' => 'Distributor Name',
                'koordinator_name' => 'Koordinator Name',
                'warehouse_name' => 'Warehouse Name',
                'total_item' => 'Total Item',
                'total_qty' => 'Total Qty',
                'total_qty_sale' => 'Total Qty Sale',
                'total_qty_return' => 'Total Qty Return',
                'grand_total' => 'Grand Total',
                'sale_total' => 'Sale Total',
                'return_total' => 'Return Total',
                'margin_total' => 'Margin Total',
                'paid' => 'Paid',
                'updated_by_name' => 'Updated By Name',
                'updated_at' => 'Updated At',
            ],
        ],
        'monitor_absensi_motoris' => [
            'title' => 'Monitor Absensi Motoris',
            'columns' => [
                'no' => 'No',
                'nama_sales_motoris' => 'Nama Sales Motoris',
                'nama_koordinator' => 'Nama Koordinator',
                'nama_depo' => 'Nama Depo',
                'hari_kerja' => 'Hari Kerja',
                'total_kehadiran' => 'Total Kehadiran',
                'total_tidak_hadir' => 'Total Tidak Hadir',
            ],
        ],
        'kota_kabupaten' => [
            'title' => 'Kota Kabupaten',
            'columns' => [
                'source_id' => 'ID',
                'provinsi' => 'Provinsi',
                'kode' => 'Kode',
                'nama_kota_kabupaten' => 'Nama Kota / Kabupaten',
            ],
        ],
        'lap_absensi_koordinator' => [
            'title' => 'Lap. Absensi Koordinator',
            'columns' => [
                'source_id' => 'ID',
                'date' => 'Date',
                'time_in' => 'Time In',
                'time_out' => 'Time Out',
                'employee_name' => 'Employee Name',
                'employee_phone' => 'Employee Phone',
                'category_name' => 'Category Name',
                'warehouse_name' => 'Warehouse Name',
            ],
        ],
        'absensi_sales_terlambat' => [
            'title' => 'Absensi Sales Terlambat',
            'columns' => [
                'nama_sales' => 'Nama Sales',
                'koordinator' => 'Koordinator',
                'depo' => 'Depo',
                'tanggal' => 'Tanggal',
                'jam_masuk' => 'Jam Masuk',
                'jam_wajib' => 'Jam Wajib',
                'telat' => 'Telat',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'user_sales' => [
            'title' => 'User Sales',
            'columns' => [
                'username' => 'Username',
                'nickname' => 'Nickname',
                'karyawan' => 'Karyawan',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'status' => 'Status',
                'keterangan' => 'Keterangan',
                'login_terakhir_pada' => 'Login Terakhir',
            ],
        ],
        'karyawan_sales' => [
            'title' => 'Karyawan Sales',
            'columns' => [
                'source_id' => 'ID',
                'nama_lengkap' => 'Nama Lengkap',
                'koordinator' => 'Koordinator',
                'depo' => 'Depo',
                'area' => 'Area',
                'total_area' => 'Total Area',
                'no_hp' => 'No HP',
                'status' => 'Status',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_user' => [
            'title' => 'Lap. User',
            'columns' => [
                'source_id' => 'ID',
                'nickname' => 'Nickname',
                'username' => 'Username',
                'status' => 'Status',
                'created_at' => 'Created At',
                'updated_at' => 'Updated At',
                'employee_name' => 'Employee Name',
                'employee_active' => 'Employee Active',
                'employee_phone' => 'Employee Phone',
                'user_category_name' => 'User Category Name',
                'warehouse_name' => 'Warehouse Name',
                'created_by_name' => 'Created By Name',
                'updated_by_name' => 'Updated By Name',
            ],
        ],
        'lap_karyawan' => [
            'title' => 'Lap. Karyawan',
            'columns' => [
                'source_id' => 'ID',
                'fullname' => 'Fullname',
                'email' => 'Email',
                'phone' => 'Phone',
                'active' => 'Active',
                'employment_start_date' => 'Employment Start Date',
                'basic_salary' => 'Basic Salary',
                'fuel_and_meal_allowance' => 'Fuel And Meal Allowance',
                'motor_rent' => 'Motor Rent',
                'internet_data_allowance' => 'Internet Data Allowance',
                'position_allowance' => 'Position Allowance',
                'created_at' => 'Created At',
                'updated_at' => 'Updated At',
                'employee_category_name' => 'Employee Category Name',
                'warehouse_name' => 'Warehouse Name',
                'area_name' => 'Area Name',
                'koordinator_name' => 'Koordinator Name',
                'created_by_name' => 'Created By Name',
                'updated_by_name' => 'Updated By Name',
            ],
        ],
        'lap_outlet_noo' => [
            'title' => 'Lap. Outlet NOO',
            'sumber' => SumberOutletNoo::class,
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'contact_person1' => 'Contact Person1',
                'contact_person2' => 'Contact Person2',
                'phone1' => 'Phone1',
                'phone2' => 'Phone2',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'category_name' => 'Category Name',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'warehouse_name' => 'Warehouse Name',
                'koordinator_name' => 'Koordinator Name',
                'status' => 'Status Verifikasi',
            ],
        ],
        'outlet_duplikat' => [
            'title' => 'Outlet Duplikat / Fiktif',
            'columns' => [
                'kode_outlet_fiktif' => 'Kode Outlet Fiktif',
                'nama_outlet_fiktif' => 'Nama Outlet Fiktif',
                'alamat' => 'Alamat',
                'area' => 'Area',
                'kode_outlet_original' => 'Kode Outlet Original',
                'nama_outlet_original' => 'Nama Outlet Original',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_outlet_fiktif' => [
            'title' => 'Lap. Outlet Fiktif',
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'original_outlet_code' => 'Original Outlet Code',
                'original_outlet_name' => 'Original Outlet Name',
                'fiktif_created_by_name' => 'Fiktif Created By Name',
                'fiktif_created_at' => 'Fiktif Created At',
                'original_created_by_name' => 'Original Created By Name',
                'original_created_at' => 'Original Created At',
                'updated_at' => 'Updated At',
            ],
        ],
        'lap_stok_kompetitor' => [
            'title' => 'Lap. Stok Kompetitor',
            'sumber' => SumberStokKompetitor::class,
            'columns' => [
                'source_id' => 'ID',
                'quantity' => 'Quantity',
                'last_update' => 'Last Update',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_checkin' => 'Date Checkin',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'duration' => 'Duration',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'product_name' => 'Product Name',
                'sumber' => 'Sumber',
            ],
        ],
        'retur_barang' => [
            'title' => 'Retur Barang',
            'columns' => [
                'kode_retur' => 'Kode Retur',
                'tanggal' => 'Tanggal',
                'no_loading' => 'No. Loading',
                'distributor' => 'Distributor',
                'gudang' => 'Gudang',
                'total_item' => 'Total Item',
                'total_qty' => 'Total Qty',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'loading_barang' => [
            'title' => 'Loading Barang',
            'columns' => [
                'kode_transaksi' => 'Kode Transaksi',
                'tanggal' => 'Tanggal',
                'distributor' => 'Distributor',
                'gudang' => 'Gudang',
                'total_item' => 'Total Item',
                'total_qty' => 'Total Qty',
                'status_retur' => 'Status Retur',
                'grand_total' => 'Grand Total',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'absensi_koordinator' => [
            'title' => 'Absensi Koordinator',
            'columns' => [
                'nama_karyawan' => 'Nama Karyawan',
                'depo' => 'Depo',
                'tanggal' => 'Tanggal',
                'jam_masuk' => 'Jam Masuk',
                'jam_keluar' => 'Jam Keluar',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_retur_barang_detail' => [
            'title' => 'Lap. Retur Barang Detail',
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'purchase_trans_code' => 'Purchase Trans Code',
                'distributor_name' => 'Distributor Name',
                'koordinator_name' => 'Koordinator Name',
                'warehouse_name' => 'Warehouse Name',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'product_unit' => 'Product Unit',
                'quantity' => 'Quantity',
                'cost' => 'Cost',
                'discount' => 'Discount',
                'total' => 'Total',
                'total_discount' => 'Total Discount',
            ],
        ],
        'lap_loading_barang_detail' => [
            'title' => 'Lap. Loading Barang Detail',
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'distributor_name' => 'Distributor Name',
                'koordinator_name' => 'Koordinator Name',
                'warehouse_name' => 'Warehouse Name',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'product_unit' => 'Product Unit',
                'quantity' => 'Quantity',
                'cost' => 'Cost',
                'discount' => 'Discount',
                'total' => 'Total',
                'total_discount' => 'Total Discount',
                'sale_quantity' => 'Sale Quantity',
                'return_quantity' => 'Return Quantity',
                'sale_total' => 'Sale Total',
                'return_total' => 'Return Total',
            ],
        ],
        'lap_absensi_sales' => [
            'title' => 'Lap. Absensi Sales',
            'columns' => [
                'source_id' => 'ID',
                'date' => 'Date',
                'time_in' => 'Time In',
                'time_out' => 'Time Out',
                'employee_name' => 'Employee Name',
                'employee_phone' => 'Employee Phone',
                'koordinator_name' => 'Koordinator Name',
                'warehouse_name' => 'Warehouse Name',
            ],
        ],
        'lap_checkin_checkout' => [
            'title' => 'Lap. Checkin Checkout',
            'columns' => [
                'source_id' => 'ID',
                'attendance_no' => 'Attendance No',
                'attendance_date' => 'Attendance Date',
                'checkin_time' => 'Checkin Time',
                'checkout_time' => 'Checkout Time',
                'duration' => 'Duration',
                'attendance_status' => 'Attendance Status',
                'location_long' => 'Location Long',
                'location_lat' => 'Location Lat',
                'warehouse_name' => 'Warehouse Name',
                'employee_name' => 'Employee Name',
                'updated_by_name' => 'Updated By Name',
                'updated_at' => 'Updated At',
            ],
        ],
        'kecamatan' => [
            'title' => 'Kecamatan',
            'columns' => [
                'source_id' => 'ID',
                'provinsi' => 'Provinsi',
                'kota_kabupaten' => 'Kota / Kabupaten',
                'kode' => 'Kode',
                'nama_kecamatan' => 'Nama Kecamatan',
            ],
        ],
        'lap_join_visit' => [
            'title' => 'Lap. Join Visit',
            'columns' => [
                'source_id' => 'ID',
                'koordinator_name' => 'Koordinator Name',
                'sales_name' => 'Sales Name',
                'inspection_date' => 'Inspection Date',
                'start_time' => 'Start Time',
                'end_time' => 'End Time',
                'description' => 'Description',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'photo' => 'Photo',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'updated_by_name' => 'Updated By Name',
                'updated_at' => 'Updated At',
            ],
        ],
        'login_session' => [
            'title' => 'Sesi Login',
            'sumber' => SumberSesiLogin::class,
            'columns' => [
                'username_nickname' => 'Username / Nickname',
                'kategori' => 'Kategori',
                'depo' => 'Depo',
                'nama_karyawan' => 'Nama Karyawan',
                'id_karyawan' => 'ID Karyawan',
                'nama_koordinator' => 'Nama Koordinator',
                'device_token' => 'Device / Token',
                'login_pada' => 'Login Pada',
                'terakhir_aktif' => 'Terakhir Aktif',
            ],
        ],
        // Dibaca dari area_polygon (peta area Enesis), bukan salinan datar master_area: area baru ikut tampil.
        'master_area' => [
            'title' => 'Master Area',
            'sumber' => SumberMasterArea::class,
            'columns' => [
                'kode' => 'Kode',
                'nama' => 'Nama',
                'total_toko' => 'Total Toko',
                'nama_sales' => 'Nama Sales',
                'depo' => 'Depo',
                'wilayah_depo' => 'Wilayah Depo',
                'provinsi' => 'Provinsi',
                'kota_kabupaten' => 'Kota / Kabupaten',
                'kecamatan' => 'Kecamatan',
                'kelurahan' => 'Kelurahan',
                'jarak_terdekat_ke_depo' => 'Jarak Terdekat ke Depo',
                'jarak_terjauh_ke_depo' => 'Jarak Terjauh ke Depo',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_barang_habis_terjual' => [
            'title' => 'Lap. Barang Habis Terjual',
            'sumber' => SumberBarangHabisTerjual::class,
            'columns' => [
                'source_id' => 'ID',
                'canvassing_no' => 'Canvassing No',
                'warehouse_name' => 'Warehouse Name',
                'area_name' => 'Area Name',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'receive_date' => 'Receive Date',
                'product_sku' => 'Product SKU',
                'product_name' => 'Product Name',
                'category_name' => 'Category Name',
                'unit_name' => 'Unit Name',
                'product_unit' => 'Product Unit',
                'total_qty' => 'Total Qty',
                'sale_qty' => 'Sale Qty',
                'return_qty' => 'Return Qty',
                'sisa_qty' => 'Sisa Qty',
                'status_terjual' => 'Status Terjual',
                'pct_terjual' => 'Pct Terjual',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_pengembalian_barang' => [
            'title' => 'Lap. Pengembalian Barang',
            'sumber' => SumberPengembalianBarang::class,
            'columns' => [
                'source_id' => 'ID',
                'canvas_return_no' => 'Canvas Return No',
                'canvassing_no' => 'Canvassing No',
                'return_date' => 'Return Date',
                'return_datetime' => 'Return Datetime',
                'warehouse_name' => 'Warehouse Name',
                'area_name' => 'Area Name',
                'salesperson_name' => 'Salesperson Name',
                'product_sku' => 'Product SKU',
                'product_name' => 'Product Name',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'product_unit' => 'Product Unit',
                'total_qty' => 'Total Qty',
                'sale_qty' => 'Sale Qty',
                'return_qty' => 'Return Qty',
                'sumber' => 'Sumber',
            ],
        ],
        'barang_canvassing' => [
            'title' => 'Daftar Muatan Motoris',
            'sumber' => SumberCanvassingMuatan::class,
            // source_id (ID muatan) tidak ditampilkan, tetapi tetap ada di baris untuk membuka drawer detail (Menu/Show.vue).
            'columns' => [
                'canvassing_no' => 'No. Muatan',
                'warehouse_name' => 'Depo',
                'salesperson_name' => 'Motoris',
                'koordinator_name' => 'Koordinator',
                'status' => 'Status',
                'receive_datetime' => 'Waktu Diterima',
                'return_datetime' => 'Waktu Dikembalikan',
                // Jumlah SKU / total qty bawa / total qty diterima dalam satu kolom (lihat SumberCanvassingMuatan::ubahBaris).
                'ringkasan_muatan' => 'SKU / Qty Bawa / Qty Diterima',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_barang_canvassing' => [
            'title' => 'Lap. Barang Canvassing',
            'sumber' => SumberCanvassing::class,
            'columns' => [
                'source_id' => 'ID',
                'canvassing_no' => 'Canvassing No',
                'warehouse_name' => 'Warehouse Name',
                'area_name' => 'Area Name',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'receive_date' => 'Receive Date',
                'receive_datetime' => 'Receive Datetime',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'product_unit' => 'Product Unit',
                'total_qty' => 'Total Qty',
                'sale_qty' => 'Sale Qty',
                'return_qty' => 'Return Qty',
                'returned' => 'Returned',
                'sumber' => 'Sumber',
            ],
        ],
        'kelurahan' => [
            'title' => 'Kelurahan',
            'columns' => [
                'source_id' => 'ID',
                'provinsi' => 'Provinsi',
                'kota_kabupaten' => 'Kota / Kabupaten',
                'kecamatan' => 'Kecamatan',
                'kode' => 'Kode',
                'nama_kelurahan' => 'Nama Kelurahan',
            ],
        ],
        'lap_outlet_oss' => [
            'title' => 'Lap. Outlet OSS',
            'sumber' => SumberOutletOss::class,
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'contact_person1' => 'Contact Person1',
                'contact_person2' => 'Contact Person2',
                'phone1' => 'Phone1',
                'phone2' => 'Phone2',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'category_name' => 'Category Name',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'warehouse_name' => 'Warehouse Name',
                'koordinator_name' => 'Koordinator Name',
            ],
        ],
        'lap_effective_call' => [
            'title' => 'Lap. Efective Call',
            'sumber' => SumberEffectiveCall::class,
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'total_item' => 'Total Item',
                'total_qty' => 'Total Qty',
                'grand_total' => 'Grand Total',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_longitude' => 'Outlet Longitude',
                'outlet_latitude' => 'Outlet Latitude',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'checkin_duration' => 'Checkin Duration',
                'sales_longitude' => 'Sales Longitude',
                'sales_latitude' => 'Sales Latitude',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_penjualan_barang' => [
            'title' => 'Lap. Penjualan Barang',
            'sumber' => SumberPenjualan::class,
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'effective_call_status' => 'Effective Call Status',
                'total_item' => 'Total Item',
                'total_qty' => 'Total Qty',
                'grand_total' => 'Grand Total',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_longitude' => 'Outlet Longitude',
                'outlet_latitude' => 'Outlet Latitude',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'checkin_duration' => 'Checkin Duration',
                'sales_longitude' => 'Sales Longitude',
                'sales_latitude' => 'Sales Latitude',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_outlet_ac' => [
            'title' => 'Lap. Outlet AC',
            'sumber' => SumberOutletAc::class,
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'contact_person1' => 'Contact Person1',
                'contact_person2' => 'Contact Person2',
                'phone1' => 'Phone1',
                'phone2' => 'Phone2',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'category_name' => 'Category Name',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'warehouse_name' => 'Warehouse Name',
                'koordinator_name' => 'Koordinator Name',
            ],
        ],
        'lap_checkin_outlet' => [
            'title' => 'Lap. Checkin Outlet',
            'columns' => [
                'source_id' => 'ID',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_checkin' => 'Date Checkin',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'duration' => 'Duration',
                'description' => 'Description',
                'category' => 'Category',
                'photo' => 'Photo',
                'sales_longitude' => 'Sales Longitude',
                'sales_latitude' => 'Sales Latitude',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_longitude' => 'Outlet Longitude',
                'outlet_latitude' => 'Outlet Latitude',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
            ],
        ],
        'lap_outlet_ec' => [
            'title' => 'Lap. Outlet EC',
            'sumber' => SumberOutletEc::class,
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'contact_person1' => 'Contact Person1',
                'contact_person2' => 'Contact Person2',
                'phone1' => 'Phone1',
                'phone2' => 'Phone2',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'category_name' => 'Category Name',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'warehouse_name' => 'Warehouse Name',
                'koordinator_name' => 'Koordinator Name',
            ],
        ],
        'lap_effective_call_barang' => [
            'title' => 'Lap. Efective Call Detail',
            'sumber' => SumberEffectiveCallItem::class,
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'quantity' => 'Quantity',
                'price' => 'Price',
                'discount' => 'Discount',
                'total' => 'Total',
                'total_discount' => 'Total Discount',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_longitude' => 'Outlet Longitude',
                'outlet_latitude' => 'Outlet Latitude',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'checkin_duration' => 'Checkin Duration',
                'sales_longitude' => 'Sales Longitude',
                'sales_latitude' => 'Sales Latitude',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_penjualan_barang_detail' => [
            'title' => 'Lap. Penjualan Barang Detail',
            'sumber' => SumberPenjualanItem::class,
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'effective_call_status' => 'Effective Call Status',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'quantity' => 'Quantity',
                'price' => 'Price',
                'discount' => 'Discount',
                'total' => 'Total',
                'total_discount' => 'Total Discount',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_longitude' => 'Outlet Longitude',
                'outlet_latitude' => 'Outlet Latitude',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'checkin_duration' => 'Checkin Duration',
                'sales_longitude' => 'Sales Longitude',
                'sales_latitude' => 'Sales Latitude',
                'sumber' => 'Sumber',
            ],
        ],
        // 'sumber': kelas App\Support\Menu\SumberTabel yang membuat query (join ke master area/depo/karyawan).
        'mk_outlet' => [
            'title' => 'Master Outlet',
            'sumber' => SumberOutletMaster::class,
            'columns' => [
                'id' => 'ID',
                'kode' => 'Kode Outlet',
                'nama' => 'Nama Outlet',
                'alamat' => 'Alamat',
                'area' => 'Area',
                'depo' => 'Depo',
                'provinsi' => 'Provinsi',
                'kota_kabupaten' => 'Kota/Kabupaten',
                'kecamatan' => 'Kecamatan',
                'kelurahan' => 'Kelurahan',
                'latitude' => 'Latitude',
                'longitude' => 'Longitude',
                'verifikasi' => 'Verifikasi',
                'status_fiktif' => 'Status Fiktif',
                'ditandai' => 'Ditandai',
                'dibuat_oleh' => 'Dibuat Oleh',
                'dibuat_pada' => 'Dibuat Pada',
            ],
        ],
        'trx_outlet_baru' => [
            'title' => 'Outlet Baru dari HP',
            'sumber' => SumberOutletBaru::class,
            // Tombol Setujui / Tolak + tautan foto (Menu/Show.vue), untuk akun dengan hak ubah outlet_all.
            'aksi' => 'verifikasi_outlet_baru',
            'columns' => [
                'id' => 'ID',
                'status' => 'Status',
                'nama' => 'Nama Outlet',
                'pemilik' => 'Pemilik',
                'no_hp' => 'No. HP',
                'tipe' => 'Tipe',
                'alamat' => 'Alamat',
                'sales' => 'Sales',
                'depo' => 'Depo',
                'area' => 'Area',
                'lat' => 'Latitude',
                'lng' => 'Longitude',
                'outlet_id' => 'ID Master Outlet',
                'recorded_at' => 'Dicatat di HP',
                'alasan_tolak' => 'Alasan Tolak',
            ],
        ],
        'trx_join_visit' => [
            'title' => 'Join Visit',
            'sumber' => SumberJoinVisit::class,
            // Tombol Foto (Menu/Show.vue) bila ada foto_id; cukup hak lihat.
            'aksi' => 'foto',
            'aksi_hak' => 'lihat',
            'columns' => [
                'id' => 'ID',
                'tanggal' => 'Tanggal',
                'koordinator' => 'Koordinator',
                'sales' => 'Sales',
                'kode_outlet' => 'Kode Outlet',
                'outlet' => 'Outlet',
                'area' => 'Area',
                'depo' => 'Depo',
                'mulai' => 'Mulai',
                'selesai' => 'Selesai',
                'durasi' => 'Durasi',
                'jarak_m' => 'Jarak ke Outlet (m)',
                'catatan' => 'Catatan',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_outlet_all' => [
            'title' => 'Lap. Outlet All',
            'sumber' => SumberOutletStatus::class,
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'contact_person1' => 'Contact Person1',
                'contact_person2' => 'Contact Person2',
                'phone1' => 'Phone1',
                'phone2' => 'Phone2',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'category_name' => 'Category Name',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'warehouse_name' => 'Warehouse Name',
                'koordinator_name' => 'Koordinator Name',
            ],
        ],
        'lap_stok_outlet' => [
            'title' => 'Lap. Stok Outlet',
            'sumber' => SumberStokOutlet::class,
            'columns' => [
                'source_id' => 'ID',
                'quantity' => 'Quantity',
                'unit_name' => 'Unit Name',
                'last_update' => 'Last Update',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_checkin' => 'Date Checkin',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'duration' => 'Duration',
                'category' => 'Category',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'product_code' => 'Product Code',
                'product_name' => 'Product Name',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'sumber' => 'Sumber',
            ],
        ],
        'rekap_pencapaian_sku_motoris_tableachieved' => [
            'title' => 'Rekap Pencapaian SKU per Motoris - tableAchieved',
            'columns' => [
                'no' => 'No',
                'depo' => 'Depo',
                'motoris' => 'Motoris',
                'total_qty' => 'Total Qty',
                'hari_kerja' => 'Hari Kerja',
                'avg_day' => 'Avg/Day',
                'kategori' => 'Kategori',
            ],
        ],
        'rekap_pencapaian_sku_motoris_tablenotachieved' => [
            'title' => 'Rekap Pencapaian SKU per Motoris - tableNotAchieved',
            'columns' => [
                'no' => 'No',
                'depo' => 'Depo',
                'motoris' => 'Motoris',
                'total_qty' => 'Total Qty',
                'hari_kerja' => 'Hari Kerja',
                'avg_day' => 'Avg/Day',
                'kategori' => 'Kategori',
            ],
        ],
        'rekap_pencapaian_depo_sku_tableachieved' => [
            'title' => 'Rekap Pencapaian SKU per Depo - tableAchieved',
            'columns' => [
                'no' => 'No',
                'depo' => 'Depo',
                'total_qty' => 'Total Qty',
                'hari_kerja' => 'Hari Kerja',
                'avg_day_motoris' => 'Avg/Day /Motoris',
                'kategori' => 'Kategori',
            ],
        ],
        'rekap_pencapaian_depo_sku_tablenotachieved' => [
            'title' => 'Rekap Pencapaian SKU per Depo - tableNotAchieved',
            'columns' => [
                'no' => 'No',
                'depo' => 'Depo',
                'total_qty' => 'Total Qty',
                'hari_kerja' => 'Hari Kerja',
                'avg_day_motoris' => 'Avg/Day /Motoris',
                'kategori' => 'Kategori',
            ],
        ],
        'motoris_coverage' => [
            'title' => 'Motoris Coverage',
            'columns' => [
                '11_city' => '11 CITY',
                'quota_motoris' => 'QUOTA MOTORIS',
                'alreadytransaction' => 'AlreadyTransaction',
                'active_end_periode' => 'ACTIVE END PERIODE',
                'vacant' => 'VACANT',
                'coverage' => 'COVERAGE %',
            ],
        ],
        'motoris_performance' => [
            'title' => 'Motoris Performance',
            'columns' => [
                '11_city' => '11 City',
                'motoris_kuota' => 'Motoris Kuota',
                'motoris_already_transaction' => 'Motoris Already Transaction',
                'motoris_actual_end_periode' => 'Motoris Actual End Periode',
                'hk_standar' => 'HK Standar',
                'act_hk' => 'Act HK',
                'target' => 'Target',
                'call' => 'Call',
                'ec' => 'EC',
                'ipt' => 'IPT',
                'value' => 'Value',
            ],
        ],
        'template_barang_canvassing' => [
            'title' => 'Template Barang Canvassing',
            'columns' => [
                'source_id' => 'ID',
                'nama_template' => 'Nama Template',
                'deskripsi' => 'Deskripsi',
                'jumlah_item' => 'Jumlah Item',
                'diubah_oleh' => 'Diubah Oleh',
            ],
        ],
        'panduan' => [
            'title' => 'Panduan',
            'columns' => [
                'no' => 'No',
                'informasi' => 'Informasi',
                'diupdate_pada' => 'Diupdate Pada',
                'diupdate_oleh' => 'Diupdate Oleh',
                'kolom' => '',
            ],
        ],
        'waktu_aktif_app' => [
            'title' => 'Hari Kerja Aktif',
            'columns' => [
                'no' => 'No',
                'nama_hari' => 'Nama Hari',
                'waktu_mulai' => 'Waktu Mulai',
                'waktu_berakhir' => 'Waktu Berakhir',
                'sales' => 'Sales',
                'koordinator' => 'Koordinator',
                'update_terakhir' => 'Update Terakhir',
                'kolom' => '',
            ],
        ],
        'target_setting' => [
            'title' => 'Setting Target',
            'columns' => [
                'no' => 'No',
                'nama_target' => 'Nama Target',
                'kategori' => 'Kategori',
                'jumlah_target' => 'Jumlah Target',
                'kolom' => '',
            ],
        ],
        'kategori_user' => [
            'title' => 'Kategori User',
            'columns' => [
                'source_id' => 'ID',
                'nama_kategori' => 'Nama Kategori',
                'scope_data' => 'Scope Data',
                'permissions' => 'Permissions',
            ],
        ],
        'kategori_karyawan' => [
            'title' => 'Kategori Karyawan',
            'columns' => [
                'source_id' => 'ID',
                'kategori' => 'Kategori',
            ],
        ],
        'kategori_outlet' => [
            'title' => 'Kategori Outlet',
            'columns' => [
                'source_id' => 'ID',
                'nama_kategori' => 'Nama Kategori',
                'deskripsi' => 'Deskripsi',
            ],
        ],
        'rumus_insentif_point_sales_0' => [
            'title' => 'Rumus Insentif Poin Sales - Rumus Insentif Point Sales',
            'columns' => [
                'source_id' => 'ID',
                'deskripsi' => 'Deskripsi',
                'point' => 'Point',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'rumus_insentif_point_sales_1' => [
            'title' => 'Rumus Insentif Poin Sales - Target Minimal Poin per hari dan Maksimal Poin per hari untuk mendapatkan insentif',
            'columns' => [
                'source_id' => 'ID',
                'minimal_poin_per_hari' => 'Minimal Poin per Hari',
                'maksimal_poin_per_hari' => 'Maksimal Poin per Hari',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'rumus_insentif_sales' => [
            'title' => 'Rumus Insentif Sales',
            'columns' => [
                'source_id' => 'ID',
                'deskripsi' => 'Deskripsi',
                'tot_ec_lower_bound' => 'Tot. EC Lower Bound',
                'tot_ec_upper_bound' => 'Tot. EC Upper Bound',
                'total_insentif' => 'Total Insentif',
            ],
        ],
        'rumus_insentif_koordinator' => [
            'title' => 'Rumus Insentif Koordinator',
            'columns' => [
                'source_id' => 'ID',
                'deskripsi' => 'Deskripsi',
                'tot_ec_lower_bound' => 'Tot. EC Lower Bound',
                'tot_ec_upper_bound' => 'Tot. EC Upper Bound',
                'total_sales' => 'Total Sales',
                'total_insentif' => 'Total Insentif',
            ],
        ],
    ],
];
