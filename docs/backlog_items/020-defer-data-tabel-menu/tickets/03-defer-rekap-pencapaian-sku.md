## Parent

docs/backlog_items/020-defer-data-tabel-menu/spec.md

## What to build

Terapkan pola defer + skeleton (dari tiket 01) ke `RekapPencapaianSkuController` (halaman Rekap Pencapaian SKU per Motoris & per Depo, dirender bertab lewat tiket 015 kalau sudah berjalan).

## Acceptance criteria

- [ ] `GET /rekap_pencapaian_sku` menampilkan halaman (tab, judul) tanpa menunggu query rekap SKU; area tabel menampilkan skeleton sampai data datang.
- [ ] Pindah tab (per Motoris / per Depo) mengikuti perilaku partial reload yang sudah ada — tidak menampilkan skeleton ulang (konsisten dengan tiket 01).
- [ ] Nilai rekap pencapaian & target yang ditampilkan identik dengan sebelum perubahan (regresi nilai).
- [ ] Simpan target (`POST /rekap_pencapaian_sku/target`) tidak terpengaruh — tetap request biasa, tidak di-defer.

## Implementation steps

- [ ] Identifikasi prop berat di `RekapPencapaianSkuController@index` (query rekap per motoris/depo + target) dan bungkus dengan `Inertia::defer(...)` mengikuti pola tiket 01.
- [ ] Tambah skeleton (reuse `TableSkeleton.vue`) di halaman Vue terkait, dibungkus `<Deferred>`.
- [ ] Update test terkait (`tests/Feature/.../RekapPencapaianSkuTest.php` — cek nama aktual) untuk assert nilai tidak berubah setelah defer diterapkan.
- [ ] Verifikasi manual: buka Rekap Pencapaian SKU, lihat skeleton lalu data, pindah tab per Motoris/per Depo, simpan target tetap berfungsi.
- [ ] Jalankan `php artisan test`, `npm run build`, `vendor/bin/pint`.

## Blocked by

docs/backlog_items/020-defer-data-tabel-menu/tickets/01-pola-defer-skeleton-menu-generik.md
