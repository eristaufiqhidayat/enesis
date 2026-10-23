## Parent

docs/backlog_items/006-util-format-tanggal-tampilan/spec.md

## What to build

Sambungkan util JS dari tiket 01 ke organism `resources/js/Components/ui/organisms/DataTable.vue`, mengikuti pola `badgeColumns` yang sudah ada (`DataTable.vue:12,19-21,37`):

1. Tambah dua prop baru: `dateColumns: { type: Array, default: () => [] }` (kolom bertipe `date`) dan `datetimeColumns: { type: Array, default: () => [] }` (kolom bertipe `datetime`/timestamp, mis. `created_at`, `updated_at`).
2. Di `<template>`, kalau nama kolom ada di `dateColumns`, render `formatTanggal(row[column])`; kalau ada di `datetimeColumns`, render `formatTanggalWaktu(row[column])` (keduanya diimpor dari `resources/js/utils/date.js`) sebagai ganti nilai mentah.
3. Prioritas render per sel: `badgeColumns` lalu `dateColumns`/`datetimeColumns` lalu default teks mentah — jangan ubah urutan/perilaku `badgeColumns` yang sudah ada. Satu nama kolom tidak boleh ada di lebih dari satu dari ketiga daftar ini sekaligus (anggap sebagai kesalahan pemakaian, tidak perlu divalidasi runtime).
4. Tidak perlu mengubah `MenuController` atau `config/marketama.php` untuk benar-benar menandai kolom tanggal/datetime pada menu tertentu — itu rollout per menu yang di luar cakupan (lihat Out of Scope di spec). Tiket ini hanya menyediakan mekanismenya di komponen.

## Acceptance criteria

- [ ] `DataTable.vue` punya prop `dateColumns` dan `datetimeColumns`, masing-masing merender kolom lewat `formatTanggal`/`formatTanggalWaktu`.
- [ ] Kolom yang tidak ada di salah satu daftar tetap tampil seperti sebelumnya (tidak ada regresi).
- [ ] `badgeColumns` tetap berfungsi seperti sebelumnya saat dipakai bersamaan dengan `dateColumns`/`datetimeColumns` pada kolom berbeda.
- [ ] Ditest lewat test Inertia/Vue yang sudah ada (atau test baru) yang memverifikasi rendering sel tanggal dan datetime secara terpisah.

## Blocked by

- Tiket 01 (`01-util-js-format-tanggal.md`) — butuh `formatTanggal` dan `formatTanggalWaktu` sudah tersedia di `resources/js/utils/date.js`.
