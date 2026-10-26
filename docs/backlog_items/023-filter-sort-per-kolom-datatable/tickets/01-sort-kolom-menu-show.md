## Parent

docs/backlog_items/023-filter-sort-per-kolom-datatable/spec.md

## What to build

Tracer-bullet end-to-end paling sempit: sort satu kolom (klik header) untuk tabel tanpa `SumberTabel` (query langsung `DB::table($table)`) di `Menu/Show.vue` lewat `MenuController@show`. Ini membuktikan jalur query-string → backend `orderBy` whitelist → `DataTable` UI klik-untuk-sort bekerja end-to-end, sebelum ticket lain menambah filter per kolom dan dukungan `SumberTabel`.

## Acceptance criteria

- [x] `DataTable.vue` menerima prop `sortable: Array` (nama kolom yang boleh di-sort); header kolom yang namanya ada di `sortable` dirender bisa diklik dengan ikon arah (netral/asc/desc).
- [ ] Klik header kolom sortable mengganti query string `?sort[<table>]=<kolom>&arah[<table>]=asc`, klik lagi → `desc`, klik lagi → sort dihapus (kembali netral/default). Reload Inertia hanya bagian `tables` (pakai `only`, pola sama dengan `Pagination`).
- [x] `MenuController@muatTabel`: untuk tabel tanpa `sumber` (query `DB::table($table)` langsung), terapkan `orderBy($kolom, $arah)` bila `sort[<table>]` ada di request **dan** kolomnya ada di `array_keys($columns)` (whitelist); kolom di luar whitelist diabaikan (fallback ke `orderBy('id')` seperti sekarang).
- [x] Key baru opsional `sortable` (array nama kolom) ditambahkan di `config/marketama.php` sejajar dengan `title`/`columns` per tabel. Tabel yang tidak mengisi key ini (mayoritas tabel, termasuk yang sudah ada sekarang) tidak punya kolom sortable sama sekali — tidak ada perubahan perilaku untuk tabel yang belum dikonfigurasi developer.
- [ ] `Menu/Show.vue` mengisi prop `sortable` ke `<DataTable>` dari `sortable_columns` yang dikirim backend per tabel (hasil baca key config `sortable`, default `[]` bila tidak diisi).
- [x] Sort hanya berlaku ke tabel yang query string-nya namespace sesuai nama tabel itu; tabel lain di halaman bertab yang sama tidak terpengaruh.
- [x] Test feature baru: sort asc/desc pada kolom valid mengubah urutan baris; sort pada nama kolom yang tidak ada di whitelist diabaikan tanpa error.
- [ ] `npm test`, `composer test`, `vendor/bin/pint` lulus. Verifikasi manual di browser pada satu menu generik (mis. menu kecil tanpa `sumber`).

## Implementation steps

- [x] `resources/js/Components/ui/organisms/DataTable.vue`: tambah prop `sortable`, render header sebagai tombol/klik-able dengan ikon arah (gunakan class baru `.data-table th.sortable` + ikon dari `Icon` atom, bukan utility Tailwind inline), emit `@sort` atau langsung `router.get`/`router.reload` dengan query string baru saat diklik (ikuti pola reload yang sudah dipakai `Pagination.vue`).
- [x] `resources/css/app.css`: tambah class `@layer components` untuk header sortable (hover state, ikon arah aktif).
- [x] `app/Http/Controllers/MenuController.php`: baca key config `sortable` per tabel (`config("marketama.tables.{$table}.sortable", [])`) sebagai whitelist (bukan `array_keys($columns)` — kolom yang ada di `columns` tapi tidak masuk `sortable` tetap tidak bisa di-sort). Baca `sort[<table>]`/`arah[<table>]` dari `$request`, validasi terhadap whitelist itu, terapkan `orderBy` sebelum `paginate` untuk cabang tanpa `$sumber` (baris `$query = $sumber ? ... : DB::table($table)` dan `($sumber ? $query : $query->orderBy('id'))->paginate(...)`). Kirim whitelist itu ke frontend sebagai `sortable_columns` di array tabel yang sudah dikembalikan `muatTabel`.
- [x] `resources/js/Pages/Menu/Show.vue`: teruskan prop `sortable` (catatan: nilai sort aktif dikirim per tabel lewat `sort` di payload `tables`, bukan lewat prop halaman seperti `tanggal`/`filter`) dan nilai sort/arah aktif (dari `tanggal`/`filter` prop pattern yang sudah ada) ke `<DataTable>` per tabel.
- [x] Tambah/duplikasi test feature di `tests/Feature/` (cari test `MenuController` yang sudah ada, mis. terkait pencarian `?q=`, untuk pola yang sama) yang menguji sort asc/desc dan whitelist.
- [ ] Jalankan `npm test`, `composer test`, `vendor/bin/pint`; cek manual di browser. (catatan: npm test, `php artisan test` 222/222, dan pint lulus; cek browser belum berhasil karena server dev tidak merespons di sesi ini.)

## Blocked by

Tidak ada.
