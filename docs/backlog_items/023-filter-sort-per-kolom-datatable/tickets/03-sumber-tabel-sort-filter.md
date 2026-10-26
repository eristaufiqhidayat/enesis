## Parent

docs/backlog_items/023-filter-sort-per-kolom-datatable/spec.md

## What to build

Perluas sort & filter per kolom (ticket 01 & 02) ke tabel yang pakai `SumberTabel` (join, mis. `OutletAll` di menu `outlet_all`), supaya menu dengan data join juga bisa di-sort/filter per kolom, bukan hanya tabel `DB::table($table)` langsung.

## Acceptance criteria

- [ ] Interface/abstract `App\Support\Menu\SumberTabel` punya method baru untuk melaporkan kolom yang valid untuk sort (`kolomSort(): array`) dan filter (`kolomFilter(): array`), **default kosong (`[]`)** bila kelas turunan tidak override — konsisten dengan model opt-in di ticket 01/02 (tidak fallback otomatis ke `kolomCari()`, supaya sort/filter tetap mati sampai developer secara sadar mengaktifkannya per kelas `SumberTabel`).
- [ ] `SumberTabel` punya method untuk menerapkan `orderBy` dan `where`/LIKE ke query join-nya sendiri (mis. `terapkanSort(Builder $query, string $kolom, string $arah)` dan `terapkanFilter(Builder $query, array $filter)`), supaya tiap turunan (`OutletAll`, dll.) bisa map nama kolom tampilan ke kolom/alias SQL join yang benar.
- [ ] `MenuController@muatTabel`: cabang `$sumber` (bukan hanya cabang `DB::table` yang sudah dikerjakan ticket 01/02) memanggil method baru ini, dengan whitelist dari `kolomSort()`/`kolomFilter()` milik sumber tersebut, bukan `array_keys($columns)` mentah.
- [ ] `OutletAll` (atau `SumberTabel` turunan lain yang sudah ada) diupdate untuk override method baru ini sesuai kolom join yang dipunya (mis. nama area/depo/karyawan dari tabel master, bukan kolom `mk_outlet` mentah).
- [ ] Test feature: sort & filter per kolom bekerja di menu yang pakai `SumberTabel` (mis. `outlet_all`), termasuk kolom hasil join (nama depo/area), tidak hanya kolom tabel utama.
- [ ] `npm test`, `composer test`, `vendor/bin/pint` lulus. Verifikasi manual di browser pada menu `outlet_all`.

## Implementation steps

- [ ] `app/Support/Menu/SumberTabel.php` (interface/abstract): tambah method `kolomSort()`, `kolomFilter()` (default `[]`) dan `terapkanSort()`, `terapkanFilter()` dengan implementasi default sederhana (LIKE/`orderBy` langsung ke nama kolom) yang hanya dipanggil bila kolomnya memang ada di `kolomSort()`/`kolomFilter()` milik kelas itu — kelas turunan yang tidak override tetap aman (whitelist kosong = tidak ada yang bisa di-sort/filter), kelas yang mau opt-in cukup override kedua method whitelist tadi.
- [ ] Cari semua kelas yang implement `SumberTabel` (mis. `OutletAll` — lihat referensi di `config/marketama.php` kunci `sumber`) dan override method baru untuk kolom join yang relevan.
- [ ] `app/Http/Controllers/MenuController.php`: ubah cabang `$sumber` di `muatTabel` untuk memanggil `kolomSort()`/`kolomFilter()` sebagai whitelist dan `terapkanSort()`/`terapkanFilter()` pada `$query` sebelum `paginate`.
- [ ] Test feature baru atau tambahan di `tests/Feature/` untuk menu `outlet_all` (atau menu `SumberTabel` lain yang paling mudah ditest) mencakup sort/filter kolom hasil join.
- [ ] Jalankan `npm test`, `composer test`, `vendor/bin/pint`; cek manual di browser.

## Blocked by

- 01-sort-kolom-menu-show.md
- 02-filter-per-kolom-menu-show.md
