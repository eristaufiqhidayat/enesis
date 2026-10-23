<?php

// Akses ke aplikasi lama Marketama untuk menarik data (php artisan marketama:tarik).
// Isi username/password akun admin di .env server; jangan pernah di-commit.
return [
    'url' => rtrim(env('MARKETAMA_URL', 'https://app.cnvmarketama.com'), '/'),
    'username' => env('MARKETAMA_USERNAME'),
    'password' => env('MARKETAMA_PASSWORD'),

    // Jeda antar permintaan (milidetik) supaya server lama tidak terbebani.
    'jeda_ms' => (int) env('MARKETAMA_JEDA_MS', 300),

    // Server membatasi 100 baris per halaman DataTables.
    'per_halaman' => 100,

    // Batas waktu per permintaan (detik). Bila timeout, halaman diperkecil otomatis.
    'timeout' => (int) env('MARKETAMA_TIMEOUT', 120),

    // Jeda sebelum mengulang halaman yang timeout (detik).
    'jeda_gagal_detik' => (int) env('MARKETAMA_JEDA_GAGAL', 10),
];
