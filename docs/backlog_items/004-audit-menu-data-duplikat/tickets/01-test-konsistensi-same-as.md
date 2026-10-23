## Parent

docs/backlog_items/004-audit-menu-data-duplikat/spec.md

## What to build

Tambahkan test otomatis yang memvalidasi konsistensi key `same_as` di `config/marketama.php` terhadap key `tables` pada seluruh entry `pages`:

1. Kelompokkan semua slug `pages` yang `tables`-nya identik (dan tidak kosong) ke dalam grup.
2. Untuk setiap grup berisi lebih dari satu slug: tiap slug dalam grup harus punya `same_as` yang mengarah ke slug lain di grup yang sama (tidak boleh `null` untuk semua anggota grup — minimal pola "menu ringkas -> menu Laporan" seperti yang sudah ada harus terdeteksi).
3. Untuk setiap `pages` entry yang `same_as`-nya tidak `null`: slug tujuan harus ada di `pages`, dan `tables` tujuan harus identik dengan `tables` asal.

Baseline saat ini (`config/marketama.php`) sudah mengandung 15 pasangan yang semuanya konsisten (lihat tabel di spec) — test ini harus lulus tanpa mengubah config, kecuali ditemukan pelanggaran nyata saat implementasi (kalau itu terjadi, perbaiki config sebagai bagian dari tiket ini dan catat di PR description).

## Acceptance criteria

- [ ] Test baru (`tests/Unit/MarketamaConfigSameAsTest.php` atau nama senada) membaca `config('marketama.pages')` dan menjalankan ketiga pengecekan di atas.
- [ ] Test lulus terhadap `config/marketama.php` saat ini tanpa perlu mengubah config (atau, kalau ditemukan pelanggaran nyata, config diperbaiki dan dijelaskan di PR).
- [ ] Test gagal secara jelas (pesan assertion menyebut slug yang bermasalah) kalau: (a) dua slug punya `tables` identik tapi tidak ada yang menandai `same_as` ke yang lain, (b) `same_as` menunjuk ke slug yang tidak ada di `pages`, atau (c) `same_as` menunjuk ke slug yang `tables`-nya ternyata berbeda.
- [ ] `php artisan test` dan `vendor/bin/pint` lulus setelah perubahan.

## Blocked by

None (can start immediately).
