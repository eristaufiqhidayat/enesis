## Parent

docs/backlog_items/020-defer-data-tabel-menu/spec.md

## What to build

Terapkan pola defer + skeleton (dari tiket 01) ke `RekapPencapaianSkuController` (halaman Rekap Pencapaian SKU per Motoris & per Depo, dirender bertab lewat tiket 015 kalau sudah berjalan).

## Acceptance criteria

- [x] `GET /rekap_pencapaian_sku` menampilkan halaman (tab, judul) tanpa menunggu query rekap SKU; area tabel menampilkan skeleton sampai data datang. *(Respons awal tanpa `hasil` terverifikasi test. Di browser, judul, tab, dan form tampil sementara area tabel menampilkan skeleton selama request tertunda.)*
- [x] Pindah tab (per Motoris / per Depo) mengikuti perilaku partial reload yang sudah ada — tidak menampilkan skeleton ulang (konsisten dengan tiket 01). *(Diverifikasi di browser: saat request tertunda, tabel lama tetap tampil tanpa skeleton, lalu data tab baru masuk.)*
- [x] Nilai rekap pencapaian & target yang ditampilkan identik dengan sebelum perubahan (regresi nilai). *(Asersi nilai lama dijalankan lewat `loadDeferredProps`; 214 test lulus.)*
- [x] Simpan target (`POST /rekap_pencapaian_sku/target`) tidak terpengaruh — tetap request biasa, tidak di-defer. *(`test_admin_mengubah_target_global` lulus; kode simpan target tidak diubah.)*

## Implementation steps

- [x] Identifikasi prop berat di `RekapPencapaianSkuController@index` (query rekap per motoris/depo + target) dan bungkus dengan `Inertia::defer(...)` mengikuti pola tiket 01. *(Hanya `hasil` (hitungan per motoris/depo) yang ditunda. Target dan periode tetap eager: satu baris lookup yang dipakai judul dan subtitle.)*
- [x] Tambah skeleton (reuse `TableSkeleton.vue`) di halaman Vue terkait, dibungkus `<Deferred>`.
- [x] Update test terkait (`tests/Feature/Admin/RekapPencapaianSkuTest.php`) untuk assert nilai tidak berubah setelah defer diterapkan.
- [x] Verifikasi manual: buka Rekap Pencapaian SKU, lihat skeleton lalu data, pindah tab per Motoris/per Depo, simpan target tetap berfungsi. *(Dilakukan di browser pada 127.0.0.1:8000: skeleton, pindah tab, Tampilkan, dan Simpan target dengan nilai tetap 5 ("Target tidak berubah.") semuanya sesuai.)*
- [x] Jalankan `php artisan test`, `npm run build`, `vendor/bin/pint`. *(`php artisan test` 214/214, `npm run build` sukses, `vendor/bin/pint --test` lulus, `npm test` 34/34.)*

## Blocked by

docs/backlog_items/020-defer-data-tabel-menu/tickets/01-pola-defer-skeleton-menu-generik.md
