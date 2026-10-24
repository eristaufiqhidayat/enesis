# Gabung Menu Monitoring Harian (Checkin Pagi, Tidak Penjualan, Tidak Absen) jadi Satu Halaman Bertab

## Problem Statement

Tiga menu di grup pertama sidebar (`config/marketama.php:17-30`) — "Sales Checkin Outlet Pagi" (`checkin_pagi`), "Sales Tidak Penjualan" (`sales_tidak_penjualan`), "Sales Tidak Absen Pagi" (`sales_tidak_absen`) — adalah tiga menu terpisah yang masing-masing hanya menampilkan satu tabel dengan struktur kolom yang sangat mirip (`tanggal_data`, `no`, `depo`, `koordinator`, `nama_sales`, ...; lihat `config/marketama.php:2067-2141`). Ketiganya sama-sama laporan "monitoring harian sales" dan kemungkinan besar selalu dicek berurutan oleh user yang sama di hari yang sama, tapi sekarang user harus klik tiga item sidebar yang berbeda dan kehilangan konteks tanggal yang sedang dilihat setiap pindah menu.

## Solution

Gabungkan ketiga menu ini menjadi satu halaman dengan satu slug sidebar, dirender dengan tab di bagian atas (satu tab per menu lama), sehingga:

1. Sidebar punya satu item menu menggantikan tiga item sekarang, mengurangi daftar menu yang harus di-scroll.
2. Pindah antar tab tidak reload query filter dari awal — tanggal yang sedang difilter (relevan untuk tab Checkin Pagi) tetap konsisten konteksnya dengan pengguna yang baru saja melihat tab lain.
3. Tab aktif tercermin di URL (query string) supaya bisa dibagikan/bookmark dan tombol back/forward browser berfungsi wajar.

## User Stories

1. Sebagai admin yang mengecek kehadiran sales pagi ini, saya ingin melihat Checkin Outlet Pagi, Sales Tidak Penjualan, dan Sales Tidak Absen Pagi dari satu halaman, supaya saya tidak perlu bolak-balik ke sidebar untuk tiga menu yang isinya saling berkaitan.
2. Sebagai admin yang sedang berada di tab "Sales Tidak Absen Pagi", saya ingin pindah ke tab "Sales Tidak Penjualan" tanpa harus mengatur ulang filter pencarian saya, supaya alur kerja saya tidak terganggu.
3. Sebagai admin yang menyalin URL halaman ini (termasuk tab yang sedang aktif) dan membagikannya ke rekan kerja, saya ingin rekan saya membuka tab yang sama saat link dibuka, supaya saya tidak perlu menjelaskan ulang klik mana yang harus dilakukan.
4. Sebagai admin yang sedang di tab "Checkin Outlet Pagi", saya ingin tetap melihat KPI card (Sudah Absen/Belum Absen/Terlambat) dan filter tanggal khusus tab ini, karena dua tab lain tidak punya KPI/filter tanggal yang sama (keduanya menampilkan kolom `tanggal_data` langsung di tabel, bukan filter per-hari).
5. Sebagai admin yang mencari nama sales tertentu di salah satu tab, saya ingin pencarian hanya berlaku pada tabel tab yang sedang aktif, supaya saya tidak bingung kalau query pencarian saya "ikut" ke tab lain yang kolomnya berbeda.
6. Sebagai developer yang menambah menu laporan harian serupa di masa depan, saya ingin pola tab ini reusable (bukan hardcode tiga tab ini saja), supaya menu monitoring harian lain bisa ditambahkan sebagai tab baru tanpa menulis ulang komponen.

## Implementation Decisions

- **Config (`config/marketama.php`):**
  - Tambah satu slug baru (usul: `monitoring_harian_sales`) di `menu` (grup pertama) menggantikan tiga entri `checkin_pagi`, `sales_tidak_penjualan`, `sales_tidak_absen` yang ada sekarang (baris 17-30).
  - Di `pages`, entri baru ini punya struktur `tables` seperti sekarang tapi tiap elemen jadi array asosiatif `{'table' => ..., 'tab_label' => ..., 'date_filter_column' => ..., 'kpi' => ...}` alih-alih string tabel polos — supaya `date_filter_column`/`kpi` per tab bisa berbeda (hanya tab `checkin_pagi` yang punya keduanya), bukan satu `date_filter_column`/`kpi` untuk seluruh page seperti sekarang (`pages.checkin_pagi.date_filter_column`, baris 753).
  - Tiga entri `pages.checkin_pagi`, `pages.sales_tidak_penjualan`, `pages.sales_tidak_absen` yang lama dihapus setelah dipindah isinya ke entri gabungan, supaya tidak ada slug lama yang masih bisa diakses dan membingungkan (lihat Testing Decisions soal redirect).
- **`MenuController@show`:**
  - Loop `foreach ($page['tables'] as $table)` (baris 27) perlu diubah untuk membaca `table`/`date_filter_column`/`kpi` per elemen alih-alih variabel page-level `$dateColumn`/`$kpi` yang sekarang dihitung sekali di awal (baris 19-24) — jadi tanggal filter dan KPI dihitung per tabel yang punya `date_filter_column`, bukan untuk seluruh page.
  - `tanggal` di query string tetap satu nilai (tidak perlu per-tab) karena baru satu tab (`checkin_pagi`) yang memakainya.
  - Tambah parameter `tab` di query string (misal `?tab=sales_tidak_absen`) yang menentukan tab mana yang aktif; default ke tab pertama kalau tidak ada/tidak valid. Dikirim ke Inertia sebagai prop `activeTab`.
  - Paginasi per tabel tetap pakai page-name berbeda (`page_{$table}`, baris 51) — pola ini sudah benar untuk multi-tabel dan tidak perlu diubah.
- **Vue (`resources/js/Pages/Menu/Show.vue`):**
  - Render tab bar di atas (tambah molecule/organism baru, misal `Tabs.vue` di `resources/js/Components/ui/molecules/` — reusable, bukan khusus tiga menu ini, sesuai aturan CLAUDE.md soal komponen atomic design) dengan satu tab per elemen `tables`.
  - Hanya `DataTable` untuk tab aktif yang ditampilkan; form pencarian/filter tanggal hanya relevan untuk tabel tab aktif (search/tanggal tetap terkirim ke query string tapi scoped secara visual ke tab yang sedang dibuka).
  - KPI card (`kpiItems`, baris 30-34 sekarang) hanya dirender kalau tab aktif adalah tab yang punya `kpi`.
  - Ganti tab memicu `router.get` baru dengan query `tab` berubah (bukan client-side show/hide semua data sekaligus), supaya data tab yang baru dibuka selalu fresh dan URL selalu mencerminkan tab aktif (mendukung User Story 3).
- **Blade stack:** menu ini dirender lewat Inertia (`Menu/Show.vue`), bukan Blade custom, jadi tidak ada perubahan di sisi `resources/views/` untuk tiga menu ini — hanya `resources/views/components/ui/organisms/sidebar.blade.php` dan Vue `Sidebar.vue` yang perlu update karena tiga item menu lama diganti satu item baru.
- **Redirect slug lama:** tambah route redirect dari `/checkin_pagi`, `/sales_tidak_penjualan`, `/sales_tidak_absen` ke `/monitoring_harian_sales?tab=<tab-terkait>` supaya bookmark/link lama (termasuk penyebutan `same_as` di menu lain yang mungkin mengarah ke slug ini) tidak 404.

## Testing Decisions

- Feature test di `tests/Feature/MenuTest.php` (pola sudah ada untuk pengujian config-driven page) yang:
  - Assert GET `/monitoring_harian_sales` mengembalikan Inertia response dengan tiga entri di prop `tables`, masing-masing dengan `name` sesuai tabel lama.
  - Assert default `activeTab` adalah tab pertama kalau query `tab` kosong/tidak valid.
  - Assert KPI prop hanya terisi saat `tab=checkin_pagi` (atau tab lain yang tidak punya `kpi` mengembalikan `kpi: null`).
  - Assert filter tanggal (`date_filter_column`) hanya diterapkan ke tabel yang punya `date_filter_column` dikonfigurasi, tabel lain di response tidak terpengaruh oleh query `tanggal`.
  - Assert request ke slug lama (`/checkin_pagi`, dst.) redirect (302) ke `/monitoring_harian_sales` dengan query `tab` yang sesuai.
- Verifikasi manual didokumentasikan di PR (belum ada test runner JS di proyek ini, konsisten dengan keputusan testing di tiket `009`/`010`): klik antar tab tanpa reload filter pencarian yang sedang diisi, cek URL berubah mengikuti tab aktif, cek sidebar hanya punya satu item menu menggantikan tiga item lama.

## Out of Scope

- Mengubah skema tabel (`checkin_pagi`, `sales_tidak_penjualan`, `sales_tidak_absen`) atau cara datanya diimpor — tiket ini murni perubahan tampilan/routing, bukan data.
- Menggabungkan menu monitoring harian lain yang belum disebut user (mis. `monitor_absensi_motoris`) ke dalam tab yang sama — hanya tiga menu yang disebutkan.
- Animasi transisi saat ganti tab — cukup perubahan state instan seperti pola collapse sidebar di tiket `009`.
- Membuat filter tanggal/KPI baru untuk tab `sales_tidak_penjualan`/`sales_tidak_absen` yang sekarang belum punya — tiket ini hanya menggabungkan UI, bukan menambah fitur filter baru ke tab yang belum punya.

## Further Notes

- Tabel `checkin_pagi`, `sales_tidak_penjualan`, `sales_tidak_absen` bukan tabel data pribadi/transaksi besar (bukan termasuk tabel yang sengaja tidak di-commit per CLAUDE.md), jadi tidak ada pertimbangan privasi tambahan untuk seeding/testing.
- Penamaan slug gabungan (`monitoring_harian_sales`) adalah usulan — bisa disesuaikan saat implementasi kalau ada nama yang lebih konsisten dengan penamaan menu lain di `config/marketama.php`.
