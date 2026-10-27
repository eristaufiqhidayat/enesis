<?php

// Sumber resmi pemerintah untuk master wilayah (provinsi → kelurahan/desa).
// Kode & nama Kemendagri diambil dari layanan "Kode Relasi BPS–Kemendagri" milik BPS
// (https://sig.bps.go.id/bridging-kode/index), yang memuat pasangan kode_bps/nama_bps dan kode_dagri/nama_dagri.
return [
    'bps' => [
        'url' => env('WILAYAH_BPS_URL') ?: 'https://sig.bps.go.id/rest-bridging/getwilayah',

        // Periode data relasi (parameter periode_merge, mis. "2025_1.2025"). Kosong = periode terbaru di BPS.
        'periode' => env('WILAYAH_BPS_PERIODE') ?: null,

        'timeout' => (int) env('WILAYAH_BPS_TIMEOUT', 60),

        // Jumlah permintaan paralel. Tingkat desa butuh ±7.300 permintaan (satu per kecamatan).
        'paralel' => (int) env('WILAYAH_BPS_PARALEL', 4),

        // Jawaban API disimpan di sini supaya putaran berikutnya tidak perlu mengunduh ulang (--segarkan untuk ambil ulang).
        'cache_dir' => storage_path('app/private/wilayah-resmi/bps'),
    ],

    // Laporan CSV hasil perbandingan disimpan di sini.
    'laporan_dir' => storage_path('app/private/wilayah-resmi/laporan'),
];
