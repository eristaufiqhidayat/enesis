## Parent

docs/backlog_items/020-defer-data-tabel-menu/spec.md

## What to build

Pola dasar defer + skeleton untuk menu config-driven generik (`MenuController@show` + `Menu/Show.vue`), yang mencakup mayoritas (~119) menu lewat satu config. Ini tiket tracer-bullet: membuktikan pola defer+skeleton jalan end-to-end sebelum digulirkan ke controller custom lain (tiket 02-04).

## Acceptance criteria

- [ ] Navigasi sidebar ke menu generik (mis. `/outlet_all`) langsung menampilkan halaman (judul, tab jika ada) tanpa menunggu query tabel; area tabel menampilkan skeleton sampai data datang.
- [ ] Begitu data datang, tabel/KPI/filter_pilihan tampil dengan nilai yang identik seperti sebelum perubahan ini (regresi nilai, bukan cuma struktur).
- [ ] Form pencarian, filter tanggal, dan dropdown depo/koordinator tetap tampil & berfungsi normal meski `tables` belum resolve (tidak lagi di-gate oleh `tables.length`).
- [ ] Partial reload yang sudah ada (klik "Cari", pindah tab via `pindahTab`, klik pagination) **tidak** menampilkan skeleton — perilaku dan kecepatannya tidak berubah dari sebelumnya.
- [ ] Halaman menu tanpa tabel (`page.same_as` atau "tidak ada tabel data yang ditarik") tetap tampil benar, tidak terjebak skeleton selamanya.

## Implementation steps

- [ ] Ubah `MenuController@show` ([app/Http/Controllers/MenuController.php:141](../../../../app/Http/Controllers/MenuController.php)): bungkus `'tables' => $tables` jadi `Inertia::defer(fn () => $tables, 'tables')`. Pastikan closure menangkap variabel yang dibutuhkan ($konfigTabel, $request, $slug, dst.) dari scope method.
- [ ] Tambah komponen `TableSkeleton.vue` di `resources/js/Components/ui/molecules/` (atomic design, sesuai `CLAUDE.md`) — baris placeholder abu-abu dengan jumlah kolom mengikuti `table.columns` kalau tersedia, atau default generik.
- [ ] Restrukturisasi `resources/js/Pages/Menu/Show.vue`: pisahkan kontrol filter (form pencarian/tanggal/dropdown) dan tab dari gate `tables.length`, supaya tetap tampil memakai info dari `page` (config statis) sebelum `tables` resolve.
- [ ] Bungkus blok render tabel (`<template v-if="tables.length">...</template>`, baris 151-187) dengan `<Deferred data="tables">` dari `@inertiajs/vue3`, slot `fallback` menampilkan `<TableSkeleton>`.
- [ ] Tambah/ubah test di `tests/Feature/MenuTest.php`: assert response awal tidak mengandung `tables` (atau mengandung marker defer), assert request dengan header partial-data Inertia yang eksplisit meminta `tables` mengembalikan data sama seperti sebelum perubahan.
- [ ] Verifikasi manual: pindah menu sidebar (skeleton muncul lalu data masuk), cari/pindah tab/pagination di dalam menu (tidak ada skeleton, perilaku sama seperti sebelumnya), menu tanpa tabel tetap tampil benar.
- [ ] Jalankan `php artisan test`, `npm run build`, `vendor/bin/pint`.

## Blocked by

Tidak ada.
