## Parent

docs/backlog_items/023-filter-sort-per-kolom-datatable/spec.md

## What to build

Tambah filter per kolom (input teks kecil di bawah/di header kolom) untuk tabel tanpa `SumberTabel`, memakai jalur query-string yang sama (namespace per tabel) yang sudah dibangun ticket 01 untuk sort.

## Acceptance criteria

- [ ] `DataTable.vue` menerima prop `filterable: Array` (nama kolom yang boleh difilter); kolom di `filterable` menampilkan input kecil (baris tambahan di bawah header, atau input di dalam `<th>` yang sama — pilih salah satu dan konsisten) dengan debounce sebelum trigger reload.
- [ ] Input filter kolom mengubah query string `?filter[<table>][<kolom>]=<nilai>`; dikosongkan → hapus key dari query string (bukan kirim string kosong).
- [ ] Key baru opsional `filterable` (array nama kolom) ditambahkan di `config/marketama.php` sejajar dengan `sortable` (ticket 01) — independen, boleh beda isi dari `sortable`. Tabel tanpa key ini tidak punya kolom filterable sama sekali.
- [ ] `MenuController@muatTabel`: baca `config("marketama.tables.{$table}.filterable", [])` sebagai whitelist, kirim ke frontend sebagai `filterable_columns`. Untuk tabel tanpa `sumber`, terapkan `where`/`LIKE` (pakai pola CAST+ILIKE untuk PostgreSQL yang sudah ada di method ini) per kolom dari `filter[<table>][<kolom>]`, hanya untuk kolom yang ada di whitelist `filterable`; kolom di luar whitelist diabaikan.
- [ ] Filter per kolom digabung AND dengan filter kolom lain dan dengan pencarian global `q` yang sudah ada (tidak saling menimpa).
- [ ] Filter per kolom tetap aktif setelah pindah halaman (pagination) dan setelah sort (ticket 01) diterapkan.
- [ ] Test feature baru: filter satu kolom mempersempit baris sesuai nilai; kombinasi filter dua kolom + `q` bekerja sebagai AND; filter dengan nama kolom di luar whitelist diabaikan.
- [ ] `npm test`, `composer test`, `vendor/bin/pint` lulus. Verifikasi manual di browser.

## Implementation steps

- [ ] `resources/js/Components/ui/organisms/DataTable.vue`: tambah prop `filterable`, render input filter per kolom (class baru `.data-table .col-filter` di `app.css`), debounce dan trigger reload dengan query string `filter[<table>][<kolom>]` (reuse helper debounce yang sudah dipakai pencarian global di `Menu/Show.vue` bila ada, atau ekstrak jadi composable bersama bila logikanya identik).
- [ ] `app/Http/Controllers/MenuController.php`: baca `filter[<table>]` (array kolom→nilai) dari request, validasi tiap key terhadap `array_keys($columns)`, terapkan `where`/`orWhereRaw` LIKE per kolom (ikuti pola `$pgsql ? ... : ...` yang sudah ada di blok pencarian `$search`).
- [ ] `resources/js/Pages/Menu/Show.vue`: teruskan prop `filterable` dan nilai filter aktif per tabel ke `<DataTable>`.
- [ ] Tambah test feature di `tests/Feature/` untuk filter per kolom + kombinasi dengan `q`.
- [ ] Jalankan `npm test`, `composer test`, `vendor/bin/pint`; cek manual di browser.

## Blocked by

- 01-sort-kolom-menu-show.md (pakai pola query-string namespace per tabel dan perubahan `muatTabel` yang sama; mengerjakan ini duluan akan konflik merge).
