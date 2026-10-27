<?php

// Sumber batas wilayah resmi (Kepmendagri 2025) untuk Generate Polygon Kelurahan.
// Dataset: https://github.com/cahyadsn/wilayah_boundaries (MIT). Berkas diambil per kota saat pertama dipakai,
// lalu disimpan di tabel batas_wilayah sehingga pemakaian berikutnya tidak perlu internet.
return [
    // Folder "db" dataset: URL raw GitHub atau mirror internal.
    'url_dasar' => env('BATAS_WILAYAH_URL') ?: 'https://raw.githubusercontent.com/cahyadsn/wilayah_boundaries/main/db',

    // Bila diisi, berkas dibaca dari folder lokal (hasil git clone repo dataset, folder "db") alih-alih diunduh.
    'direktori' => env('BATAS_WILAYAH_DIR') ?: null,

    'timeout' => (int) env('BATAS_WILAYAH_TIMEOUT', 60),
];
