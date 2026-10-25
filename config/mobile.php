<?php

// Pengaturan API aplikasi mobile Enesis (/api/mobile/v1). Nilai bertanda (konfirmasi)
// masih menunggu keputusan di Form Validasi Menu; ubah lewat .env tanpa mengubah kode.
return [
    // Versi aplikasi minimum (header X-App-Version); lebih lama => 426 upgrade_required.
    'versi_minimum' => env('MOBILE_VERSI_MINIMUM', '1.0.0'),

    // Token perangkat dicabut bila tidak dipakai sekian hari.
    'token_idle_hari' => (int) env('MOBILE_TOKEN_IDLE_HARI', 30),

    // Kunci idempotensi disimpan sekian hari.
    'idempotensi_hari' => (int) env('MOBILE_IDEMPOTENSI_HARI', 30),

    // Pembatasan: permintaan per menit per token, dan percobaan login per menit per username.
    'batas_per_menit' => (int) env('MOBILE_BATAS_PER_MENIT', 120),
    'batas_login_per_menit' => (int) env('MOBILE_BATAS_LOGIN_PER_MENIT', 10),

    // Jarak (meter). (konfirmasi)
    'radius_depo_m' => (int) env('MOBILE_RADIUS_DEPO_M', 100),
    'radius_outlet_m' => (int) env('MOBILE_RADIUS_OUTLET_M', 100),
    'radius_duplikat_m' => (int) env('MOBILE_RADIUS_DUPLIKAT_M', 20),

    // Jam aktif aplikasi dan batas masuk (WIB). (konfirmasi)
    'zona_waktu' => env('MOBILE_ZONA_WAKTU', 'Asia/Jakarta'),
    'jam_aktif_mulai' => env('MOBILE_JAM_AKTIF_MULAI', '06:00'),
    'jam_aktif_selesai' => env('MOBILE_JAM_AKTIF_SELESAI', '21:00'),
    'batas_masuk' => env('MOBILE_BATAS_MASUK', '08:00'),

    // Rentang checkin outlet pagi (WIB) untuk menu Monitoring Harian Sales › Sales Checkin Outlet Pagi:
    // sales aktif tanpa kunjungan dengan jam checkin >= mulai dan < selesai masuk daftar (sama dengan Marketama).
    'checkin_pagi_mulai' => env('MOBILE_CHECKIN_PAGI_MULAI', '09:00'),
    'checkin_pagi_selesai' => env('MOBILE_CHECKIN_PAGI_SELESAI', '11:00'),

    // Selisih jam HP vs server (menit) sebelum ditandai clock_skew.
    'toleransi_jam_menit' => 10,

    // Minimal value transaksi bila depo tidak punya nilainya (Rp). (konfirmasi: 35.000 / 25.000)
    'minimal_transaksi_default' => (int) env('MOBILE_MINIMAL_TRANSAKSI', 35000),

    // Target harian bila depo tidak punya nilainya.
    'target_default' => ['call' => 40, 'ec' => 25, 'ipt' => 3, 'omset' => 750000],

    // Poin: belum ada aturan baku; 0 = poin belum dihitung. (konfirmasi)
    'poin_per_qty_produk_fokus' => (int) env('MOBILE_POIN_PRODUK_FOKUS', 0),

    // Unggahan foto/lampiran.
    'unggah_maks_kb' => 1024,
    'unggah_disk' => env('MOBILE_UNGGAH_DISK', 'local'),

    // Alasan tidak beli (kode => label) untuk kunjungan tanpa order. (konfirmasi)
    'alasan_tidak_beli' => [
        'toko_tutup' => 'Toko tutup',
        'stok_masih_ada' => 'Stok masih ada',
        'pemilik_tidak_ada' => 'Pemilik tidak di tempat',
        'tidak_ada_uang' => 'Belum ada uang',
        'harga' => 'Harga tidak cocok',
        'pilih_kompetitor' => 'Memilih produk kompetitor',
        'lainnya' => 'Lainnya',
    ],

    // Tipe outlet bila tabel tipe_outlet kosong.
    'tipe_outlet_default' => ['Warung', 'Toko', 'Grosir', 'Minimarket', 'Kios'],

    // Pemeriksaan radius depo saat absen masuk (mockup: absen di depo).
    'absen_cek_radius' => (bool) env('MOBILE_ABSEN_CEK_RADIUS', true),

    // Tracking GPS.
    'tracking_maks_titik' => 500,
    'tracking_maks_umur_jam' => 48,
    'tracking_akurasi_ditandai_m' => 100,
];
