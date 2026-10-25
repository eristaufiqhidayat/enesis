## Parent

docs/backlog_items/020-defer-data-tabel-menu/spec.md

## What to build

Pola dasar defer + skeleton untuk menu config-driven generik (`MenuController@show` + `Menu/Show.vue`), yang mencakup mayoritas (~119) menu lewat satu config. Ini tiket tracer-bullet: membuktikan pola defer+skeleton jalan end-to-end sebelum digulirkan ke controller custom lain (tiket 02-04).

## Acceptance criteria

- [x] Navigasi sidebar ke menu generik (mis. `/outlet_all`) langsung menampilkan halaman (judul, tab jika ada) tanpa menunggu query tabel; area tabel menampilkan skeleton sampai data datang. *(Diverifikasi di browser: respons awal tanpa `tables`, screenshot saat request ditunda menampilkan judul, tab, form, dan skeleton.)*
- [x] Begitu data datang, tabel/KPI/filter_pilihan tampil dengan nilai yang identik seperti sebelum perubahan ini (regresi nilai, bukan cuma struktur). *(Diverifikasi: seluruh asersi nilai lama dijalankan ulang lewat `loadDeferredProps`, 214 test lulus.)*
- [ ] Form pencarian, filter tanggal, dan dropdown depo/koordinator tetap tampil & berfungsi normal meski `tables` belum resolve (tidak lagi di-gate oleh `tables.length`). *(Form dan filter tanggal sudah tidak lagi di-gate `tables.length`. Dropdown depo/koordinator tetap baru muncul setelah data tiba, karena opsinya berasal dari hasil query.)*
- [x] Partial reload yang sudah ada (klik "Cari", pindah tab via `pindahTab`, klik pagination) **tidak** menampilkan skeleton — perilaku dan kecepatannya tidak berubah dari sebelumnya. *(Diverifikasi di browser dengan tunda 16 detik: pindah tab dan Cari tidak menampilkan skeleton, tabel lama tetap tampil sampai data baru tiba. Pagination belum diuji di browser karena data lokal kosong.)*
- [x] Halaman menu tanpa tabel (`page.same_as` atau "tidak ada tabel data yang ditarik") tetap tampil benar, tidak terjebak skeleton selamanya. *(Diverifikasi di browser: `/home` langsung menampilkan pesan tanpa skeleton.)*

## Implementation steps

- [x] Ubah `MenuController@show` ([app/Http/Controllers/MenuController.php:141](../../../../app/Http/Controllers/MenuController.php)): bungkus `'tables' => $tables` jadi `Inertia::defer(fn () => $tables, 'tables')`. Pastikan closure menangkap variabel yang dibutuhkan ($konfigTabel, $request, $slug, dst.) dari scope method. *(Loop query dipindah ke method privat `muatTabel`, lalu `tables` dibungkus `Inertia::defer(fn () => $this->muatTabel(...), 'tables')`.)*
- [x] Tambah komponen `TableSkeleton.vue` di `resources/js/Components/ui/molecules/` (atomic design, sesuai `CLAUDE.md`) — baris placeholder abu-abu dengan jumlah kolom mengikuti `table.columns` kalau tersedia, atau default generik. *(Kolom belum diketahui selagi data ditunda, jadi memakai default generik 4 kolom × 5 baris.)*
- [x] Restrukturisasi `resources/js/Pages/Menu/Show.vue`: pisahkan kontrol filter (form pencarian/tanggal/dropdown) dan tab dari gate `tables.length`, supaya tetap tampil memakai info dari `page` (config statis) sebelum `tables` resolve. *(Form diatur oleh `adaTabelKonfig` dan `formDateFilter` dari config; dropdown depo/koordinator tetap bergantung pada data, lihat kriteria 3.)*
- [x] Bungkus blok render tabel (`<template v-if="tables.length">...</template>`, baris 151-187) dengan `<Deferred data="tables">` dari `@inertiajs/vue3`, slot `fallback` menampilkan `<TableSkeleton>`.
- [x] Tambah/ubah test di `tests/Feature/MenuTest.php`: assert response awal tidak mengandung `tables` (atau mengandung marker defer), assert request dengan header partial-data Inertia yang eksplisit meminta `tables` mengembalikan data sama seperti sebelum perubahan. *(Memakai `missing('tables')` dan `loadDeferredProps`, yang melakukan partial reload `only=tables`. Test di 10 file lain juga disesuaikan.)*
- [x] Verifikasi manual: pindah menu sidebar (skeleton muncul lalu data masuk), cari/pindah tab/pagination di dalam menu (tidak ada skeleton, perilaku sama seperti sebelumnya), menu tanpa tabel tetap tampil benar. *(Dilakukan di browser pada 127.0.0.1:8000 dengan akun admin lokal. Pagination dan dropdown depo/koordinator belum diuji karena data lokal kosong.)*
- [x] Jalankan `php artisan test`, `npm run build`, `vendor/bin/pint`. *(`php artisan test` 214/214, `npm run build` sukses, `vendor/bin/pint --test` lulus, `npm test` 34/34.)*

## Blocked by

Tidak ada.
