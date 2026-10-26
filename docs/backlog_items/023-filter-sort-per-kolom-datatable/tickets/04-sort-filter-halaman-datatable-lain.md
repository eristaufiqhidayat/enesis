## Parent

docs/backlog_items/023-filter-sort-per-kolom-datatable/spec.md

## What to build

Terapkan sort & filter per kolom (fitur `DataTable` dari ticket 01 & 02 sudah generic di komponennya) ke 4 halaman lain yang memanggil `<DataTable>` langsung di luar `Menu/Show.vue`: `Motoris/SummaryDepoPenjualan.vue`, `Kehadiran/Kunjungan.vue`, `Kehadiran/CheckinDepo.vue`, `Kehadiran/Absensi.vue` — masing-masing punya controller/query sendiri (bukan lewat `MenuController`), jadi whitelist & penerapan `orderBy`/`where` perlu ditambahkan di controller masing-masing.

## Acceptance criteria

- [ ] Keempat halaman mengisi prop `sortable`/`filterable` ke `<DataTable>` sesuai kolom yang relevan di masing-masing (tidak perlu semua kolom harus sortable/filterable — pilih yang berguna, mis. tanggal, nama, kota).
- [ ] Controller masing-masing halaman menerapkan whitelist + `orderBy`/`where` dengan pola yang sama seperti ticket 01/02 (query string `sort`, `arah`, `filter[<kolom>]` — tanpa namespace per tabel karena masing-masing halaman ini hanya satu tabel/paginator, beda dengan `Menu/Show.vue` yang bisa multi-tabel).
- [ ] Sort/filter tetap konsisten dengan filter yang sudah ada di tiap halaman (mis. filter tanggal/depo/koordinator di `Kehadiran/*`) — AND, tidak menimpa.
- [ ] Test feature untuk tiap controller yang disentuh (atau minimal satu representatif + regresi manual untuk sisanya bila waktu terbatas — catat di PR mana yang dites otomatis vs manual).
- [ ] `npm test`, `composer test`, `vendor/bin/pint` lulus. Verifikasi manual di browser pada keempat halaman.

## Implementation steps

- [ ] Identifikasi controller di belakang tiap halaman (`Motoris/SummaryDepoPenjualan.vue`, `Kehadiran/Kunjungan.vue`, `Kehadiran/CheckinDepo.vue`, `Kehadiran/Absensi.vue`) — cari lewat route atau `Inertia::render` yang cocok.
- [ ] Tiap controller: baca `sort`/`arah`/`filter[...]` dari request, whitelist terhadap kolom yang dikirim ke `DataTable` di halaman itu, terapkan ke query sebelum `paginate`.
- [ ] Tiap halaman Vue: isi prop `sortable`/`filterable` ke `<DataTable>`, teruskan nilai aktif.
- [ ] Tambah test feature untuk setidaknya satu controller representatif; untuk sisanya, verifikasi manual dan catat di deskripsi PR.
- [ ] Jalankan `npm test`, `composer test`, `vendor/bin/pint`; cek manual di browser pada keempat halaman.

## Blocked by

- 01-sort-kolom-menu-show.md
- 02-filter-per-kolom-menu-show.md
