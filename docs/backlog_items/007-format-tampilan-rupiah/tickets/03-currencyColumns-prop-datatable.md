## Parent

docs/backlog_items/007-format-tampilan-rupiah/spec.md

## What to build

Sambungkan util JS dari tiket 01 ke organism `resources/js/Components/ui/organisms/DataTable.vue`, mengikuti pola `badgeColumns` yang sudah ada (`DataTable.vue:12,19-21,37`) dan pola `dateColumns`/`datetimeColumns` dari `006-util-format-tanggal-tampilan` (kalau sudah digabung ke `main` lebih dulu — kalau belum, terapkan pola yang sama secara independen):

1. Tambah prop baru `currencyColumns: { type: Array, default: () => [] }`.
2. Di `<template>`, kalau nama kolom ada di `currencyColumns`, render `formatRupiah(row[column])` (import dari `resources/js/utils/currency.js`) sebagai ganti nilai mentah.
3. Prioritas render per sel: `badgeColumns` lalu `dateColumns`/`datetimeColumns` (kalau sudah ada) lalu `currencyColumns` lalu default teks mentah — jangan ubah urutan/perilaku prop yang sudah ada. Satu nama kolom tidak boleh ada di lebih dari satu daftar sekaligus (anggap sebagai kesalahan pemakaian, tidak perlu divalidasi runtime).
4. Tidak perlu mengubah `MenuController` atau `config/marketama.php` untuk benar-benar menandai kolom rupiah pada menu tertentu — itu rollout per menu yang di luar cakupan (lihat Out of Scope di spec). Tiket ini hanya menyediakan mekanismenya di komponen.

## Acceptance criteria

- [ ] `DataTable.vue` punya prop `currencyColumns` dan merender kolom yang namanya ada di prop itu lewat `formatRupiah`.
- [ ] Kolom yang tidak ada di `currencyColumns` tetap tampil seperti sebelumnya (tidak ada regresi).
- [ ] `badgeColumns` (dan `dateColumns`/`datetimeColumns` kalau sudah ada) tetap berfungsi seperti sebelumnya saat dipakai bersamaan dengan `currencyColumns` pada kolom berbeda.
- [ ] Ditest lewat test Inertia/Vue yang sudah ada (atau test baru) yang memverifikasi rendering sel rupiah.

## Blocked by

- Tiket 01 (`01-util-js-format-rupiah.md`) — butuh `formatRupiah` sudah tersedia di `resources/js/utils/currency.js`.
