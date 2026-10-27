<?php

// Sumber batas wilayah kelurahan/desa untuk Generate Polygon Kelurahan dan Auto Grid.
// Batas diambil per kota saat pertama dipakai, lalu disimpan di tabel batas_wilayah sehingga pemakaian
// berikutnya tidak perlu internet. Pencocokan selalu lewat kode Kemendagri kelurahan (10 digit).
return [
    // big             = layanan peta resmi Badan Informasi Geospasial (geoservices.big.go.id, ArcGIS REST),
    //                   layer "Batas Wilayah Kelurahan/Desa" skala 1:10.000.
    // kemendagri-2025 = dataset cahyadsn/wilayah_boundaries (MIT, Kepmendagri 300.2.2-2430/2025).
    'sumber' => env('BATAS_WILAYAH_SUMBER', 'big'),

    // Dipakai untuk kelurahan yang tidak ada di sumber utama (mis. desa pemekaran baru belum ada di peta BIG),
    // atau bila sumber utama gagal. Kosongkan (BATAS_WILAYAH_CADANGAN=) untuk mematikan.
    'cadangan' => env('BATAS_WILAYAH_CADANGAN', 'kemendagri-2025'),

    'big' => [
        'url' => env('BATAS_WILAYAH_BIG_URL') ?: 'https://geoservices.big.go.id/rbi/rest/services/BATASWILAYAH/Administrasi_AR_KelDesa_10K/MapServer/0/query',

        // Fitur per permintaan (batas layanan 4.000; geometri desa besar, jadi kecil saja).
        'halaman' => (int) env('BATAS_WILAYAH_BIG_HALAMAN', 100),

        // Penyederhanaan geometri di server BIG dalam derajat (0.00001 ≈ 1 m). Kosong = geometri asli.
        'sederhanakan' => env('BATAS_WILAYAH_BIG_SEDERHANAKAN') ?: null,
    ],

    // Dataset cahyadsn: folder "db", URL raw GitHub atau mirror internal.
    'url_dasar' => env('BATAS_WILAYAH_URL') ?: 'https://raw.githubusercontent.com/cahyadsn/wilayah_boundaries/main/db',

    // Bila diisi, berkas dataset dibaca dari folder lokal (hasil git clone repo dataset, folder "db") alih-alih diunduh.
    'direktori' => env('BATAS_WILAYAH_DIR') ?: null,

    'timeout' => (int) env('BATAS_WILAYAH_TIMEOUT', 60),
];
