# Gabung Menu Motoris Coverage, Motoris Performance, Monitor Absensi Motoris jadi Satu Halaman Bertab

## Problem Statement

Tiga menu di grup pertama sidebar (`config/marketama.php:37-49`) — "Motoris Coverage" (`motoris_coverage`), "Motoris Performance" (`motoris_performance`), "Monitor Absensi Motoris" (`monitor_absensi_motoris`) — adalah tiga menu terpisah yang masing-masing hanya menampilkan satu tabel (`config/marketama.php:792-812`), dan ketiganya adalah tiga sudut pandang berbeda dari performa motoris yang sama (coverage area, performa penjualan, kehadiran). Kolom tiap tabelnya berbeda satu sama lain (lihat `config/marketama.php:2191-2202` & `2972-2998`), jadi ini bukan penggabungan data, melainkan penggabungan navigasi: user yang sedang mengevaluasi satu motoris/area kemungkinan besar perlu cek ketiga laporan ini berurutan, tapi sekarang harus klik tiga item sidebar berbeda dan kehilangan konteks pencarian setiap pindah menu.

Pola penggabungan menu bertab sudah dibangun di tiket [011](../011-gabung-menu-monitoring-harian-tab/spec.md) ("Monitoring Harian Sales") dan berlaku untuk kasus "satu tab = satu tabel" — persis kebutuhan di sini (ketiga menu ini masing-masing cuma satu tabel), jadi tiket ini tidak perlu generalisasi tambahan seperti tiket [015](../015-gabung-rekap-pencapaian-sku-tab/spec.md) (yang butuh satu tab berisi lebih dari satu tabel).

## Solution

Gabungkan ketiga menu ini menjadi satu halaman dengan satu slug sidebar, dirender dengan tab di bagian atas (satu tab per menu lama: "Coverage", "Performance", "Absensi"), sehingga:

1. Sidebar punya satu item menu menggantikan tiga item sekarang, mengurangi daftar menu yang harus di-scroll.
2. Pindah antar tab tidak reload query pencarian dari awal.
3. Tab aktif tercermin di URL (query string `?tab=`) supaya bisa dibagikan/bookmark dan tombol back/forward browser berfungsi wajar.

## User Stories

1. Sebagai admin yang mengevaluasi performa motoris, saya ingin melihat Coverage, Performance, dan Absensi dari satu halaman, supaya saya tidak perlu bolak-balik ke sidebar untuk tiga menu yang isinya saling berkaitan.
2. Sebagai admin yang sedang berada di tab "Absensi", saya ingin pindah ke tab "Performance" tanpa harus mengatur ulang pencarian saya, supaya alur kerja saya tidak terganggu.
3. Sebagai admin yang menyalin URL halaman ini (termasuk tab yang sedang aktif) dan membagikannya ke rekan kerja, saya ingin rekan saya membuka tab yang sama saat link dibuka, supaya saya tidak perlu menjelaskan ulang klik mana yang harus dilakukan.
4. Sebagai admin yang mencari nama motoris/depo tertentu di salah satu tab, saya ingin pencarian hanya berlaku pada tabel tab yang sedang aktif, supaya saya tidak bingung kalau query pencarian saya "ikut" ke tab lain yang kolomnya sama sekali berbeda (ketiga tabel tidak punya kolom yang sama).
5. Sebagai developer yang menambah menu laporan motoris serupa di masa depan, saya ingin menu ini tetap mengikuti pola tab reusable yang sudah ada (bukan implementasi baru), supaya menu motoris lain bisa ditambahkan sebagai tab baru tanpa menulis ulang komponen.

## Implementation Decisions

- **Config (`config/marketama.php`):**
  - Tambah satu slug baru (usul: `motoris_report`) di `menu` (grup pertama) menggantikan tiga entri `motoris_coverage`, `motoris_performance`, `monitor_absensi_motoris` yang ada sekarang (baris 37-49).
  - Di `pages`, entri baru ini set `'tabs' => true` dengan tiga elemen `tables`, masing-masing satu tabel dengan `tab_label` (`Coverage`, `Performance`, `Absensi`) — mengikuti struktur grup `{tab_key, tab_label, tables: [...]}` dari tiket 011/015 kalau tiket 015 sudah berjalan lebih dulu, atau struktur `{table, tab_label}` dari tiket 011 kalau belum (keduanya kompatibel untuk kasus satu tabel per tab).
  - Tiga entri `pages.motoris_coverage`, `pages.motoris_performance`, `pages.monitor_absensi_motoris` yang lama dihapus setelah dipindah isinya ke entri gabungan (lihat Testing Decisions soal redirect).
- **`MenuController@show`:** tidak perlu perubahan logika baru — pakai mekanisme tab yang sudah ada (dari tiket 011, atau versi general dari tiket 015 kalau sudah ada saat implementasi). Tidak ada `date_filter_column`/`kpi` untuk ketiga tabel ini.
- **Vue (`resources/js/Pages/Menu/Show.vue`):** tidak perlu perubahan komponen — `Tabs.vue`/`DataTable.vue` yang sudah ada dipakai langsung.
- **Blade stack:** menu ini dirender lewat Inertia (`Menu/Show.vue`), bukan Blade custom, jadi tidak ada perubahan di sisi `resources/views/` untuk tiga menu ini — hanya `resources/views/components/ui/organisms/sidebar.blade.php` dan Vue `Sidebar.vue` yang perlu update karena tiga item menu lama diganti satu item baru.
- **Redirect slug lama:** tambah route redirect dari `/motoris_coverage`, `/motoris_performance`, `/monitor_absensi_motoris` ke `/motoris_report?tab=<tab-terkait>` supaya bookmark/link lama tidak 404.

## Testing Decisions

- Feature test di `tests/Feature/MenuTest.php` (pola sudah ada dari tiket 011) yang:
  - Assert GET `/motoris_report` mengembalikan Inertia response dengan prop `tables` berisi tiga entri, masing-masing sesuai tabel lama.
  - Assert default `activeTab` adalah tab pertama (`Coverage`) kalau query `tab` kosong/tidak valid.
  - Assert query `tab=` untuk masing-masing tab lain hanya menampilkan tabel milik tab itu, bukan ketiganya sekaligus.
  - Assert request ke slug lama (`/motoris_coverage`, `/motoris_performance`, `/monitor_absensi_motoris`) redirect (302) ke `/motoris_report` dengan query `tab` yang sesuai.
- Verifikasi manual didokumentasikan di PR (belum ada test runner JS di proyek ini, konsisten dengan tiket 009/010/011): klik antar tab tanpa reload filter pencarian yang sedang diisi, cek URL berubah mengikuti tab aktif, cek sidebar hanya punya satu item menu menggantikan tiga item lama.

## Out of Scope

- Mengubah skema tabel (`motoris_coverage`, `motoris_performance`, `monitor_absensi_motoris`) atau cara datanya diimpor — tiket ini murni perubahan tampilan/routing, bukan data.
- Menyatukan kolom ketiga tabel (mis. kolom motoris/depo yang sama nama tapi beda penulisan) menjadi satu skema — ketiga tabel tetap tampil dengan kolomnya masing-masing, tiket ini tidak mengubah data.
- Menggabungkan menu motoris lain yang belum disebut user (mis. "Summary All Depo") ke dalam tab yang sama — hanya tiga menu yang disebutkan.
- Animasi transisi saat ganti tab — cukup perubahan state instan seperti pola tab di tiket 011.

## Further Notes

- Tabel `motoris_coverage`, `motoris_performance`, `monitor_absensi_motoris` bukan tabel data pribadi/transaksi besar, jadi tidak ada pertimbangan privasi tambahan untuk seeding/testing.
- Kalau tiket ini dikerjakan setelah tiket 015 selesai (mekanisme tab sudah digeneralisasi jadi "satu tab = satu grup tabel"), konfigurasi tiga tab di sini tinggal dibuat sebagai tiga grup berisi satu tabel masing-masing — tidak ada perubahan mekanisme tambahan yang dibutuhkan dari tiket ini.
- Penamaan slug gabungan (`motoris_report`) adalah usulan — bisa disesuaikan saat implementasi kalau ada nama yang lebih konsisten dengan penamaan menu lain di `config/marketama.php`.
