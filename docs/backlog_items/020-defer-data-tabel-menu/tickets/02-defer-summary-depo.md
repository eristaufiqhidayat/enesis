## Parent

docs/backlog_items/020-defer-data-tabel-menu/spec.md

## What to build

Terapkan pola defer + skeleton (dari tiket 01) ke `SummaryDepoController` (halaman Summary All Depo: kartu per depo + detail per koordinator + penjualan).

## Acceptance criteria

- [x] `GET /summary_all_depo` menampilkan halaman (judul, filter jika ada) tanpa menunggu query kartu per depo; area kartu/tabel menampilkan skeleton sampai data datang. *(Respons awal tanpa `kartu` diverifikasi test. Skeleton lalu data kartu diverifikasi manual di browser oleh pemilik tiket.)*
- [x] `GET /summary_all_depo/{depo}` dan `GET /summary_all_depo/{depo}/penjualan` mengikuti pola yang sama untuk bagian detail/penjualan yang berat. *(Diverifikasi test: `total`/`tim` dan `total`/`baris` ditunda, dimuat lewat `loadDeferredProps`.)*
- [x] Nilai KPI, target, dan data penjualan yang ditampilkan identik dengan sebelum perubahan (regresi nilai). *(Asersi nilai lama dijalankan ulang lewat `loadDeferredProps`, 7/7 test `SummaryDepoTest` lulus; suite penuh 214/214.)*
- [x] Simpan target (`POST /summary_all_depo/target`) tidak terpengaruh — tetap request biasa (bukan bagian dari initial visit), tidak di-defer. *(Controller `simpanTarget` tidak diubah; `test_admin_mengubah_target_nasional` lulus.)*

## Implementation steps

- [x] Identifikasi prop berat di `SummaryDepoController@index`/`detail`/`penjualan` (query kartu per depo, join ke target nasional) dan bungkus dengan `Inertia::defer(...)` mengikuti pola tiket 01. *(Kartu di `index`; `total`/`tim` di `detail`; `total`/`baris` di `penjualan`. Lookup depo ringan dipisah ke `SummaryDepo::depo()` agar judul dan 404 tidak menjalankan query berat.)*
- [x] Tambah skeleton (reuse `TableSkeleton.vue` dari tiket 01, atau varian kartu kalau layoutnya beda) di halaman Vue terkait, dibungkus `<Deferred>`. *(Nama aktual ada di `resources/js/Pages/Motoris/`, bukan `Admin/`. Ditambah `Motoris/partials/KartuDepoSkeleton.vue` untuk kartu; file ini belum di-commit.)*
- [x] Update test terkait (`tests/Feature/Admin/SummaryDepoTest.php`) untuk assert nilai tidak berubah setelah defer diterapkan. *(Nama aktual: `tests/Feature/Admin/SummaryDepoTest.php`.)*
- [ ] Verifikasi manual: buka Summary All Depo, lihat skeleton lalu data kartu, klik detail depo, klik penjualan, simpan target tetap berfungsi. *(Sebagian di browser: halaman `/summary_all_depo` termuat tanpa error dan tombol Chart aktif setelah data tiba. Belum diuji: skeleton kartu (data lokal kosong, respons terlalu cepat), detail depo dan penjualan (tidak ada depo di database lokal), dan simpan target.)*
- [x] Jalankan `php artisan test`, `npm run build`, `vendor/bin/pint`. *(`php artisan test` 214/214; `npm run build` sukses; `vendor/bin/pint --test` lulus; `npm test` 34/34. `composer` tidak ada di PATH, jadi script `test` dijalankan manual: `config:clear` lalu `artisan test`.)*

## Blocked by

docs/backlog_items/020-defer-data-tabel-menu/tickets/01-pola-defer-skeleton-menu-generik.md
