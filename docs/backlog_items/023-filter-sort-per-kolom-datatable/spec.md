# Filter & Sort per Kolom di DataTable

## Problem Statement

Organism `DataTable` ([resources/js/Components/ui/organisms/DataTable.vue](../../../resources/js/Components/ui/organisms/DataTable.vue)) saat ini hanya punya satu pencarian global (`?q=`) yang dicocokkan ke semua kolom `kolomCari` sekaligus ([app/Http/Controllers/MenuController.php](../../../app/Http/Controllers/MenuController.php)), dan tidak ada cara mengurutkan baris selain urutan default (`id` naik, atau urutan dari `SumberTabel`). Pengguna tidak bisa mempersempit satu kolom tertentu (mis. cari "kota = Jakarta" saja) atau mengurutkan tabel (mis. urutkan `value` dari terbesar), padahal tabel di aplikasi ini sering berisi ratusan–ribuan baris per tabel Marketama.

`DataTable` dipakai di 5 halaman: `Menu/Show.vue` (lewat `MenuController@show`, menu config-driven generik), `Motoris/SummaryDepoPenjualan.vue`, `Kehadiran/Kunjungan.vue`, `Kehadiran/CheckinDepo.vue`, `Kehadiran/Absensi.vue`.

## Solution

Tambah dua kemampuan baru pada `DataTable` (organism bersama) dan alur data di belakangnya, tanpa mengubah cara halaman memanggil `<DataTable>` di luar prop baru yang opsional:

1. **Sort per kolom**: header kolom yang bisa diurutkan jadi dapat diklik, menampilkan ikon arah (naik/turun/netral). Klik mengganti query string `?sort=<kolom>&arah=asc|desc` (toggle asc → desc → netral saat kolom yang sama diklik ulang) dan Inertia reload hanya bagian tabel (pola sama seperti `Pagination` — pakai `only`).
2. **Filter per kolom**: baris input kecil di bawah header kolom (atau di header yang sama, lihat Implementation Decisions) untuk kolom yang `filterable`. Mengetik di satu kolom menambah `?filter[<kolom>]=<nilai>` ke query string, debounce sebelum reload (pola sama seperti pencarian global yang sudah ada di `Menu/Show.vue`), dan digabung dengan filter kolom lain + pencarian global `q` yang sudah ada (AND).
3. Backend (`MenuController@muatTabel`, dan `SumberTabel`/turunannya seperti `OutletAll`) menerima `sort`, `arah`, dan `filter[...]` dari request, memvalidasi nama kolom terhadap `columns` tabel tersebut (whitelist, supaya tidak bisa order by/where kolom sembarangan), lalu menerapkannya ke query sebelum paginate.
4. Fitur ini **opt-in per tabel**, bukan aktif otomatis: developer yang menentukan menu/tabel mana yang punya sort dan/atau filter per kolom, dan kolom mana saja. Tabel yang tidak dikonfigurasi tetap berperilaku seperti sekarang (tidak ada header yang bisa diklik, tidak ada input filter kolom). Konfigurasi kolom per tabel (`config/marketama.php` → `tables.<table>`) diberi dua key baru yang opsional, `sortable` dan `filterable` (array nama kolom, lihat Implementation Decisions) — tanpa key ini sama sekali, tabel tersebut tidak punya sort/filter kolom sama sekali (bukan "default semua kolom aktif"). Developer bisa mengaktifkan hanya sort, hanya filter, keduanya, atau tidak sama sekali, dan boleh berbeda daftar kolom antara sort dan filter (mis. kolom `tanggal` sortable tapi tidak filterable).

## User Stories

1. Sebagai admin yang membuka menu dengan ratusan baris, saya ingin mengetik nilai di kolom tertentu untuk mempersempit baris yang cocok di kolom itu saja, tanpa harus memakai pencarian global yang mencocokkan semua kolom.
2. Sebagai admin, saya ingin klik judul kolom (mis. "Tanggal" atau "Value") untuk mengurutkan tabel naik/turun, supaya saya bisa langsung melihat data terbaru atau nilai terbesar/terkecil.
3. Sebagai admin, saya ingin filter dan sort per kolom tetap ada setelah pindah halaman (pagination) atau refresh, karena tersimpan di URL (query string).
4. Sebagai developer yang menambah/mengubah menu, saya ingin bisa menyalakan sort dan/atau filter per kolom hanya untuk tabel dan kolom yang saya pilih (lewat config), tanpa fitur ini otomatis muncul di semua tabel/menu yang sudah ada — supaya tabel yang belum siap (mis. kolom hasil join yang rumit, atau tabel dengan volume kecil yang tidak butuh ini) tidak terpengaruh dan tidak perlu diaudit satu-satu.

## Implementation Decisions

- Prop baru di `DataTable.vue`: `sortable: { type: Array, default: () => [] }` (daftar nama kolom yang boleh di-sort) dan `filterable: { type: Array, default: () => [] }` (daftar nama kolom yang boleh difilter). **Default `[]` berarti fitur mati** — komponen tidak menambah header klik-able atau input filter apa pun kecuali diminta lewat prop ini, jadi halaman lama yang belum disentuh otomatis tetap berperilaku seperti sekarang. Halaman yang memanggil `<DataTable>` langsung (`Kehadiran/*`, `Motoris/SummaryDepoPenjualan.vue`) menentukan sendiri array ini per pemanggilan; `Menu/Show.vue` mengisinya dari prop baru yang dikirim backend per tabel (`sortable_columns`, `filterable_columns`, dibaca dari key config `sortable`/`filterable` milik tabel itu di `marketama.php` — kosong/tidak ada di config → array kosong dikirim ke frontend → fitur mati untuk tabel itu).
- Di `marketama.php`, key `sortable`/`filterable` ditulis per tabel (sejajar dengan `title`, `columns`), bukan per kolom di dalam `columns` — supaya menambahkan sort/filter ke satu tabel adalah perubahan config satu baris (`'sortable' => ['tanggal', 'nama'], 'filterable' => ['nama', 'kota']`) tanpa menyentuh definisi `columns` yang sudah ada.
- State sort & filter per kolom dikirim lewat query string, bukan prop lokal komponen, mengikuti pola `q`/`tanggal`/`depo` yang sudah ada — supaya konsisten dengan bookmark/share URL dan `Pagination` yang sudah pakai `only`.
- Di `MenuController`, karena satu halaman bisa menampilkan beberapa tabel sekaligus (tab atau grup), query string filter/sort diberi namespace per tabel: `?sort[<table>]=<kolom>&arah[<table>]=asc&filter[<table>][<kolom>]=<nilai>` — supaya sort/filter tabel A tidak bentrok dengan tabel B saat keduanya tampil di halaman yang sama (lihat `muatTabel` yang looping banyak `$konfig`).
- Validasi whitelist kolom sort/filter dilakukan di server (`in_array($kolom, $sortable/$filterable, true)`), bukan percaya input user, supaya tidak ada SQL injection lewat nama kolom dinamis (`orderBy`/`where` dengan nama kolom dari request).
- Tabel yang pakai `SumberTabel` (join, mis. `OutletAll`) perlu method baru di interface/abstract `SumberTabel` untuk melaporkan kolom sort/filter yang valid dan menerapkan `orderBy`/`where` ke query join-nya (kolom dari tabel join, bukan hanya tabel utama).
- Filter per kolom pakai pencocokan "LIKE %nilai%" untuk kolom teks (konsisten dengan pencarian global yang sudah ada), dan exact match untuk kolom yang nilainya enum/angka kecil bila ada info tipe kolom; untuk versi pertama ini, cukup satu strategi LIKE/CAST+ILIKE yang sudah ada di `muatTabel`, diterapkan per kolom alih-alih gabungan OR semua kolom.
- Komponen `DataTable` tetap single-root dan memakai class komponen baru di `app.css` (`@layer components`, pola `.data-table th.sortable`, `.data-table .col-filter`) untuk ikon sort dan input filter kecil, sesuai aturan styling di `CLAUDE.md`/`docs/STYLE_GUIDE.md` — bukan utility Tailwind panjang inline.
- Debounce input filter kolom pakai pola yang sama dengan input pencarian global yang sudah ada di `Menu/Show.vue` (cek implementasinya untuk konsistensi delay).

## Testing Decisions

- Test komponen (`DataTable.spec.js`, dibuat baru bila belum ada) untuk: header kolom `sortable` menampilkan ikon dan emit event yang benar saat diklik (naik → turun → netral), kolom tanpa `sortable` tidak menampilkan ikon/tidak bisa diklik, input filter kolom hanya muncul untuk kolom di prop `filterable`.
- Feature test (Pest/PHPUnit, `tests/Feature/`) untuk `MenuController@show`: sort naik/turun pada kolom valid mengubah urutan baris; sort pada kolom yang tidak ada di whitelist diabaikan (tidak error, tidak dipakai sebagai `orderBy`); filter per kolom mempersempit baris sesuai nilai; kombinasi filter per kolom + pencarian global `q` bekerja sebagai AND; sort/filter pada satu tabel di halaman bertab tidak mempengaruhi tabel lain.
- Verifikasi manual di browser: buka menu dengan data besar (mis. `outlet_all` atau menu dengan `OutletAll` sumber), klik beberapa header untuk sort, isi filter di satu-dua kolom, pastikan URL berubah, hasil benar, dan pindah halaman (pagination) tidak menghapus filter/sort yang aktif.

## Out of Scope

- Filter rentang (tanggal dari–sampai, angka min–max) per kolom — versi pertama ini hanya pencocokan teks/exact sederhana per kolom. Filter tanggal halaman (`tanggal`, `depo`, `koordinator` yang sudah ada di beberapa menu) tidak diubah.
- Multi-column sort (urutkan berdasarkan beberapa kolom sekaligus) — hanya satu kolom sort aktif per tabel.
- Mengubah tab `Motoris/Report.vue` (Coverage/Performance, agregat per kota, bukan dari `DataTable`) dan `Motoris/RekapSku.vue` — keduanya pakai tabel custom (`partials/Performance.vue`, dll.), bukan organism `DataTable`, jadi di luar scope ini.
- Export hasil filter/sort ke Excel/CSV.
- Menyimpan preferensi sort/filter per user (semua state hanya di URL, hilang saat user membuka menu dari awal tanpa query string).
- `Menu/partials/RiwayatDistributor.vue` (drawer kedua di Lap. Loading Barang) — bukan halaman Inertia, tidak berpaginasi (`limit(100)`, tanpa `:links`), dan diambil lewat `fetch()` JSON manual, bukan `router`/query string. Struktur fetch-nya terlalu berbeda dari pola sort/filter di spec ini untuk di-retrofit tanpa menulis ulang komponennya; jika dibutuhkan, itu pekerjaan tersendiri.

## Implementation Notes (2026-10-31)

Selain `Menu/Show.vue` (ticket 01–03), fitur ini sudah dipasang di 6 halaman lain yang memanggil `<DataTable>` langsung (ticket 04 & 05): `Kehadiran/Absensi.vue`, `Kehadiran/CheckinDepo.vue`, `Kehadiran/Kunjungan.vue`, `Motoris/SummaryDepoPenjualan.vue`, `Karyawan/Index.vue`, `MasterProduk/Index.vue`. Karena masing-masing controller mengelola query sendiri (bukan lewat `MenuController@muatTabel`), whitelist & penerapan `orderBy`/`where`-nya dipusatkan lewat helper baru `App\Support\Tabel\SortFilterKolom` (dipakai controller) dan `resources/js/Support/sortFilterTabel.js` (dipakai halaman Vue, pola tanpa namespace per tabel karena satu `<DataTable>` per halaman). `MasterProduk::DEFINISI` diberi key `sortable`/`filterable` per slug menu, mengikuti pola `marketama.php`.
