## Parent

docs/backlog_items/020-defer-data-tabel-menu/spec.md

## What to build

Terapkan pola defer + skeleton (dari tiket 01) ke `SummaryDepoController` (halaman Summary All Depo: kartu per depo + detail per koordinator + penjualan).

## Acceptance criteria

- [ ] `GET /summary_all_depo` menampilkan halaman (judul, filter jika ada) tanpa menunggu query kartu per depo; area kartu/tabel menampilkan skeleton sampai data datang.
- [ ] `GET /summary_all_depo/{depo}` dan `GET /summary_all_depo/{depo}/penjualan` mengikuti pola yang sama untuk bagian detail/penjualan yang berat.
- [ ] Nilai KPI, target, dan data penjualan yang ditampilkan identik dengan sebelum perubahan (regresi nilai).
- [ ] Simpan target (`POST /summary_all_depo/target`) tidak terpengaruh — tetap request biasa (bukan bagian dari initial visit), tidak di-defer.

## Implementation steps

- [ ] Identifikasi prop berat di `SummaryDepoController@index`/`detail`/`penjualan` (query kartu per depo, join ke target nasional) dan bungkus dengan `Inertia::defer(...)` mengikuti pola tiket 01.
- [ ] Tambah skeleton (reuse `TableSkeleton.vue` dari tiket 01, atau varian kartu kalau layoutnya beda) di halaman Vue terkait (`resources/js/Pages/Admin/SummaryDepo*.vue` atau sejenisnya — cek nama file aktual saat implementasi), dibungkus `<Deferred>`.
- [ ] Update test terkait (`tests/Feature/.../SummaryDepoTest.php` — cek nama aktual) untuk assert nilai tidak berubah setelah defer diterapkan.
- [ ] Verifikasi manual: buka Summary All Depo, lihat skeleton lalu data kartu, klik detail depo, klik penjualan, simpan target tetap berfungsi.
- [ ] Jalankan `php artisan test`, `npm run build`, `vendor/bin/pint`.

## Blocked by

docs/backlog_items/020-defer-data-tabel-menu/tickets/01-pola-defer-skeleton-menu-generik.md
