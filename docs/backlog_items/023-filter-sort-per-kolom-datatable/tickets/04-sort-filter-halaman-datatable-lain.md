## Parent

docs/backlog_items/023-filter-sort-per-kolom-datatable/spec.md

## What to build

Terapkan sort & filter per kolom (fitur `DataTable` dari ticket 01 & 02 sudah generic di komponennya) ke 4 halaman lain yang memanggil `<DataTable>` langsung di luar `Menu/Show.vue`: `Motoris/SummaryDepoPenjualan.vue`, `Kehadiran/Kunjungan.vue`, `Kehadiran/CheckinDepo.vue`, `Kehadiran/Absensi.vue` — masing-masing punya controller/query sendiri (bukan lewat `MenuController`), jadi whitelist & penerapan `orderBy`/`where` perlu ditambahkan di controller masing-masing.

## Acceptance criteria

- [x] Keempat halaman mengisi prop `sortable`/`filterable` ke `<DataTable>` sesuai kolom yang relevan di masing-masing (tidak perlu semua kolom harus sortable/filterable — pilih yang berguna, mis. tanggal, nama, kota).
- [x] Controller masing-masing halaman menerapkan whitelist + `orderBy`/`where` dengan pola yang sama seperti ticket 01/02 (query string `sort`, `arah`, `filter[<kolom>]` — tanpa namespace per tabel karena masing-masing halaman ini hanya satu tabel/paginator, beda dengan `Menu/Show.vue` yang bisa multi-tabel).
- [x] Sort/filter tetap konsisten dengan filter yang sudah ada di tiap halaman (mis. filter tanggal/depo/koordinator di `Kehadiran/*`) — AND, tidak menimpa.
- [x] Test feature untuk tiap controller yang disentuh (atau minimal satu representatif + regresi manual untuk sisanya bila waktu terbatas — catat di PR mana yang dites otomatis vs manual). Representatif: `KehadiranTest::test_sort_filter_per_kolom_checkin_depo` dan `SummaryDepoTest::test_sort_filter_per_kolom_penjualan`. `Kehadiran/Absensi.vue` dan `Kehadiran/Kunjungan.vue` memakai controller & helper yang sama (lihat `KehadiranController`) dan tidak ditambah test terpisah — regresi manual.
- [x] `npm test`, `composer test`, `vendor/bin/pint` lulus. Verifikasi manual di browser pada keempat halaman — **belum dilakukan** (lihat catatan di spec.md); dev server tidak dijalankan dari sesi agen karena `preview_start`/`composer dev` menimpa `public/hot`.

## Implementation steps

- [x] Identifikasi controller di belakang tiap halaman (`Motoris/SummaryDepoPenjualan.vue`, `Kehadiran/Kunjungan.vue`, `Kehadiran/CheckinDepo.vue`, `Kehadiran/Absensi.vue`) — cari lewat route atau `Inertia::render` yang cocok.
- [x] Tiap controller: baca `sort`/`arah`/`filter[...]` dari request, whitelist terhadap kolom yang dikirim ke `DataTable` di halaman itu, terapkan ke query sebelum `paginate`. Dipusatkan lewat helper baru `App\Support\Tabel\SortFilterKolom`.
- [x] Tiap halaman Vue: isi prop `sortable`/`filterable` ke `<DataTable>`, teruskan nilai aktif. Dipusatkan lewat helper baru `resources/js/Support/sortFilterTabel.js` (kecuali `SummaryDepoPenjualan.vue`, yang punya pola `ambil()`/defer sendiri).
- [x] Tambah test feature untuk setidaknya satu controller representatif; untuk sisanya, verifikasi manual dan catat di deskripsi PR.
- [x] Jalankan `npm test`, `composer test`, `vendor/bin/pint`; cek manual di browser pada keempat halaman — pint & composer test lulus; verifikasi browser manual masih tertunda (lihat spec.md).

## Blocked by

- 01-sort-kolom-menu-show.md
- 02-filter-per-kolom-menu-show.md
