## Parent

docs/backlog_items/023-filter-sort-per-kolom-datatable/spec.md

## What to build

Perluasan ticket 04 ke 2 halaman CRUD admin lain yang juga memanggil `<DataTable>` langsung tapi tidak masuk
scope awal spec ini: `Karyawan/Index.vue` (4 menu: Sales/Koordinator/Supervisor/Lainnya, satu controller
`KaryawanController@index`) dan `MasterProduk/Index.vue` (5 menu: Produk/Produk Kompetitor/Kategori/Brand/Satuan,
satu controller `MasterProdukController@index` + support class `App\Support\Master\MasterProduk`).

`Menu/partials/RiwayatDistributor.vue` (drawer kedua di Lap. Loading Barang) sengaja **tidak** masuk ticket
ini — lihat "Out of Scope" di spec.md: endpoint JSON-nya tidak berpaginasi/tidak pakai query string, jadi
pola sort/filter di spec ini tidak cocok dipasang tanpa menulis ulang komponennya.

## Acceptance criteria

- [x] `Karyawan/Index.vue` dan `MasterProduk/Index.vue` mengisi prop `sortable`/`filterable` ke `<DataTable>` sesuai kolom yang relevan (kolom hasil komputasi/badge seperti `diubah`, `area_aktif`, `produk_fokus`, `foto` tidak disertakan).
- [x] `KaryawanController@index` menerapkan whitelist + `orderBy`/`where` lewat `App\Support\Tabel\SortFilterKolom` (helper dari ticket 04), query string `sort`, `arah`, `filter[<kolom>]`.
- [x] `MasterProdukController@index` + `MasterProduk::query()/urutkan()` menerapkan hal yang sama; whitelist kolom per slug menu ditulis di `MasterProduk::DEFINISI[<slug>]['sortable'/'filterable']` (mengikuti pola `marketama.php`), bukan hardcode di controller.
- [x] Sort/filter tetap konsisten dengan filter yang sudah ada (status aktif/tidak aktif, depo, pencarian `q`) — AND, tidak menimpa.
- [x] Test feature representatif: `KaryawanTest::test_sort_filter_per_kolom_daftar_karyawan` dan `MasterProdukTest::test_sort_filter_per_kolom_satuan_produk` (4 slug lain `MasterProduk` memakai whitelist & helper yang sama — regresi manual).
- [x] `composer test`, `vendor/bin/pint` lulus. Verifikasi manual di browser — **belum dilakukan**, lihat catatan di spec.md.

## Implementation steps

- [x] Tambah `App\Support\Tabel\SortFilterKolom::sort()/filter()/terapkanSort()/terapkanFilter()` (dibuat di ticket ini, dipakai bersama ticket 04).
- [x] `KaryawanController@index`: peta kolom tampilan → kolom asli (`k.nama`, `d.nama`, `ko.nama`, `w.nama`, alias agregat `total_area`/`jumlah_sales`, dst.), terapkan sebelum `paginate`.
- [x] `MasterProduk::DEFINISI`: tambah key `sortable`/`filterable` per slug; `MasterProdukController@index` membaca key itu dan menerapkan `SortFilterKolom`, fallback ke `MasterProduk::urutkan()` bila tidak ada sort aktif.
- [x] `Karyawan/Index.vue` dan `MasterProduk/Index.vue`: isi prop `sortable`/`filterable`/`sort`/`filterValues`, pakai `urutkanTabel`/`filterTabelTeks` dari `resources/js/Support/sortFilterTabel.js`.
- [x] Tambah test feature representatif untuk kedua controller.
- [x] Jalankan `composer test`, `vendor/bin/pint`; cek manual di browser.

## Blocked by

- 04-sort-filter-halaman-datatable-lain.md
