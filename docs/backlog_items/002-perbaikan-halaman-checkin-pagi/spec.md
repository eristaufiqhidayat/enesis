# Perbaikan Halaman checkin_pagi (Filter Tanggal & Pencarian)

## Problem Statement

Halaman menu `checkin_pagi` ("Sales Checkin Outlet Pagi") menampilkan tabel `checkin_pagi` lewat catch-all `MenuController@show` tanpa filter tanggal. Tabel ini punya kolom `tanggal_data` (date) yang merekam check-in per hari, per sales — di data produksi Marketama ini bisa sampai 1,7 juta baris (lihat `docs/PRD.md` baris 24). Tanpa filter tanggal, halaman akan selalu memaparkan paginasi dari seluruh histori, bukan "check-in hari ini" yang jadi tujuan menu ini, dan query-nya makin berat seiring data bertambah.

Pencarian teks bebas (`q`, lewat input "Cari..." di `Menu/Show.vue`) sudah ada secara generik untuk semua menu config-driven, termasuk `checkin_pagi` — tapi itu tidak menggantikan kebutuhan filter tanggal, karena pencarian teks mencari di semua kolom sekaligus, bukan mempersempit ke satu hari tertentu.

## Solution

Tambah filter tanggal (`tanggal_data`) khusus untuk halaman `checkin_pagi`, dengan default hari ini, di atas pencarian teks yang sudah ada:

- Input date picker di atas tabel, default terisi tanggal hari ini (`Carbon::today()`).
- Saat halaman dibuka tanpa parameter tanggal, tabel otomatis hanya menampilkan baris dengan `tanggal_data` = hari ini.
- Pengguna bisa mengubah tanggal lewat date picker untuk melihat hari lain; hasil query ikut berubah sesuai tanggal yang dipilih.
- Pencarian teks (`q`) yang sudah ada tetap berfungsi dan dikombinasikan (AND) dengan filter tanggal — jadi pengguna bisa mencari nama sales/depo tertentu *dalam* tanggal yang sedang difilter.
- Supaya tidak mengubah 118 menu config-driven lain, filter tanggal ini dibuat sebagai opsi per-halaman di `config/marketama.php` (misal `date_filter_column`), yang hanya diisi untuk entri `checkin_pagi`. `MenuController@show` membaca opsi ini secara generik — tidak hardcode slug `checkin_pagi` di controller — supaya pola ini bisa dipakai lagi untuk menu lain yang punya kolom tanggal serupa di masa depan.
- Tambah 3 card KPI di atas tabel, khusus untuk halaman `checkin_pagi`, yang merekap tanggal yang sedang difilter (jadi ikut berubah saat tanggal diganti):
  - **Sudah Absen**: jumlah baris `checkin_pagi` pada tanggal terpilih dengan `checkin_pertama_hari_ini` terisi (bukan null/kosong).
  - **Belum Absen**: jumlah baris `checkin_pagi` pada tanggal terpilih dengan `checkin_pertama_hari_ini` null/kosong.
  - **Terlambat**: jumlah karyawan pada tanggal terpilih yang tercatat di tabel `absensi_sales_terlambat` (menu "Absensi Sales Terlambat" yang sudah ada di config, kolom `tanggal`, `nama_sales`, `jam_masuk`, `jam_wajib`, `telat`) — dipakai sebagai sumber "terlambat" yang sudah ada, bukan heuristik baru (lihat Implementation Decisions untuk detail pencocokan tanggal).
  - Ketiga angka ini dihitung dari query `COUNT`, bukan dari jumlah baris yang sedang ditampilkan di halaman tabel (yang dipaginasi 50/halaman), supaya akurat untuk keseluruhan hari itu.

## User Stories

1. Sebagai koordinator yang membuka menu "Sales Checkin Outlet Pagi", saya ingin tabel secara default hanya menampilkan data hari ini, supaya saya langsung melihat status check-in pagi ini tanpa perlu menyaring manual dari ribuan baris histori.
2. Sebagai koordinator, saya ingin mengubah tanggal lewat date picker untuk melihat rekap check-in di hari sebelumnya, supaya saya bisa menelusuri histori kalau dibutuhkan.
3. Sebagai koordinator yang memilih tanggal tertentu lalu me-refresh atau membagikan URL halaman tersebut, saya ingin tanggal yang dipilih tetap tersimpan di query string, supaya tautan yang dibagikan menunjukkan data tanggal yang sama.
4. Sebagai koordinator, saya ingin tetap bisa mencari nama sales/depo/koordinator tertentu dalam tanggal yang sedang difilter (kombinasi filter tanggal + pencarian teks yang sudah ada), supaya saya bisa menemukan sales tertentu dengan cepat di hari tersebut.
5. Sebagai koordinator yang memilih tanggal tanpa data check-in, saya ingin melihat tabel kosong dengan pesan yang jelas (bukan error), supaya saya tahu memang tidak ada data di tanggal itu.
6. Sebagai developer, saya ingin filter tanggal ini didefinisikan lewat config (bukan hardcode nama slug di controller), supaya pola yang sama bisa dipakai untuk menu lain yang punya kebutuhan serupa tanpa mengubah `MenuController`.
7. Sebagai developer yang menjalankan test suite, saya ingin test memverifikasi default "hari ini" dan filter tanggal eksplisit lewat query string, mengikuti pola `tests/Feature/MenuTest.php` yang sudah ada.
8. Sebagai koordinator yang membuka menu "Sales Checkin Outlet Pagi", saya ingin melihat 3 card KPI (Sudah Absen, Belum Absen, Terlambat) di atas tabel, supaya saya langsung tahu ringkasan kehadiran hari ini tanpa harus menghitung manual dari tabel.
9. Sebagai koordinator yang mengganti tanggal lewat date picker, saya ingin ketiga card KPI ikut berubah sesuai tanggal yang dipilih, supaya ringkasannya selalu relevan dengan data yang sedang saya lihat.
10. Sebagai koordinator, saya ingin angka "Terlambat" konsisten dengan data yang sudah ada di menu "Absensi Sales Terlambat", supaya saya tidak melihat dua sumber kebenaran yang berbeda untuk konsep yang sama.

## Implementation Decisions

- **Config** (`config/marketama.php`): tambah key opsional `date_filter_column` pada entri `pages.checkin_pagi`, nilainya nama kolom (`'tanggal_data'`). Entri `pages` lain tidak diisi (default `null`/tidak ada), jadi tidak mengubah perilaku 118 menu lainnya.
- **Controller** (`App\Http\Controllers\MenuController@show`): kalau `$page['date_filter_column']` ada, baca parameter tanggal dari `$request->query('tanggal')` (format `Y-m-d`), default ke `Carbon::today()->toDateString()` kalau parameter kosong/tidak valid. Terapkan `->whereDate($column, $tanggal)` ke query sebelum filter pencarian teks yang sudah ada, untuk tiap tabel di halaman tersebut. Kirim `tanggal` terpilih dan flag `dateFilterColumn` (atau sejenisnya) sebagai props Inertia ke halaman, supaya frontend tahu harus menampilkan date picker atau tidak.
- **Frontend** (`resources/js/Pages/Menu/Show.vue`): tambah input date picker (`type="date"`) di sebelah form pencarian yang sudah ada, muncul hanya kalau props menandakan halaman ini punya filter tanggal. Submit date picker menggabungkan parameter `tanggal` dan `q` dalam satu `router.get` (seperti `submitSearch()` yang sudah ada), supaya kombinasi filter+pencarian jalan dalam satu request dan tersimpan di query string (`preserveState`, `replace`).
- **Komponen UI**: date picker harus pakai komponen `x-ui`/Vue yang sudah ada (`Components/ui/atoms/Button.vue` untuk submit, dan kalau ada komponen input atom di Vue — kalau belum ada, buat `Input.vue` atom baru di `resources/js/Components/ui/atoms/`, konsisten dengan pasangan Blade `x-ui.atoms.input` yang sudah ada, sesuai aturan atomic design "atom baru yang dipakai berkali-kali wajib diekstrak" di `CLAUDE.md`).
- **Tidak ada migrasi baru** — kolom `tanggal_data` sudah ada di tabel `checkin_pagi` (migrasi `2026_10_22_000001_create_marketama_tables.php` baris 573).
- **Card KPI** — dihitung di `MenuController@show`, hanya untuk halaman yang punya `date_filter_column` (jadi otomatis hanya aktif di `checkin_pagi`, tanpa hardcode slug):
  - `Sudah Absen` = `DB::table('checkin_pagi')->whereDate('tanggal_data', $tanggal)->whereNotNull('checkin_pertama_hari_ini')->where('checkin_pertama_hari_ini', '!=', '')->count()`.
  - `Belum Absen` = baris pada tanggal yang sama dikurangi `Sudah Absen` (atau query `whereNull`/`where('checkin_pertama_hari_ini', '')` setara).
  - `Terlambat` = `DB::table('absensi_sales_terlambat')->where('tanggal', $tanggal->toDateString())->count()`. **Asumsi yang perlu diverifikasi saat implementasi**: kolom `tanggal` di `absensi_sales_terlambat` bertipe string (bukan `date`), jadi format string-nya harus dicocokkan dengan hasil `$tanggal->toDateString()` (`Y-m-d`) — kalau data asli Marketama memakai format tanggal lain (misal `d/m/Y`), query ini perlu disesuaikan terlebih dulu; cek dengan data sampel nyata sebelum mengandalkan pencocokan string langsung.
  - Kirim ketiga angka ini sebagai props Inertia terpisah (misal `kpi: { sudah_absen, belum_absen, terlambat }`), dikirim hanya kalau halaman punya `date_filter_column`, supaya `Menu/Show.vue` tahu kapan harus menampilkan card.
  - Frontend: 3 card dibangun dari komponen `Card.vue` yang sudah ada (molecule), ditata dalam grid flex di atas `DataTable`, satu card per angka, dengan label dan warna yang berbeda (misal lewat variant pada elemen di dalamnya) supaya mudah dibedakan.

## Testing Decisions

- Seam: HTTP routes lewat `$this->get('/checkin_pagi', [...])`, assert lewat Inertia props (`assertInertia`), mengikuti pola `MenuTest::test_menu_page_lists_table_rows` — bukan dengan query ulang ke database untuk mengecek apa yang sudah di-assert dari response.
- Test baru (bisa ditambah ke `tests/Feature/MenuTest.php` atau file baru `tests/Feature/CheckinPagiFilterTest.php`):
  - Tanpa parameter `tanggal`, hanya baris dengan `tanggal_data` = hari ini yang muncul di `tables.0.rows.data` (seed 2 baris: satu hari ini, satu kemarin → hanya satu yang muncul).
  - Dengan parameter `?tanggal=YYYY-MM-DD` eksplisit, hanya baris di tanggal tersebut yang muncul.
  - Kombinasi `?tanggal=...&q=...` menyaring baik tanggal maupun teks pencarian.
  - Menu lain (misal `/provinsi`) tetap tidak terpengaruh — tidak ada filter tanggal diterapkan ke halaman yang tidak mengisi `date_filter_column`.
  - Props `kpi` (`sudah_absen`, `belum_absen`, `terlambat`) terhitung benar untuk data seed yang mencakup kombinasi sudah absen/belum absen/terlambat pada tanggal yang sama; menu lain yang tidak mengisi `date_filter_column` tidak mengirim props `kpi`.

## Out of Scope

- Filter tanggal berupa rentang (dari-sampai) — spec ini hanya satu tanggal per request, sesuai kebutuhan "data per hari".
- Menambahkan filter tanggal ke menu lain di luar `checkin_pagi` — pola config-nya disiapkan generik, tapi pengisian untuk menu lain adalah backlog item terpisah kalau dibutuhkan.
- Perubahan pada kolom `checkin_pertama_hari_ini` (badge) yang sudah ada di `Menu/Show.vue` — tidak diubah oleh spec ini.
- Card KPI serupa untuk menu lain selain `checkin_pagi` — di luar scope spec ini.
- Mengubah atau menambah kolom di tabel `absensi_sales_terlambat` — spec ini hanya membaca (count) dari tabel tersebut, tidak mengubah strukturnya.

## Further Notes

- Karena tabel transaksi seperti `checkin_pagi` tidak ada datanya di repo (data pribadi, lihat `CLAUDE.md`), verifikasi manual butuh data dummy lewat seeder test atau `php artisan tinker` di environment lokal — bukan data asli.
- Default "hari ini" mengurangi beban query signifikan untuk tabel besar seperti ini di produksi (1,7 juta baris/tabel) — manfaat performa ini sebaiknya disebut di PR description saat implementasi.
- Angka "Terlambat" bergantung pada format kolom `tanggal` (string) di `absensi_sales_terlambat` cocok dengan format `Y-m-d` yang dipakai filter tanggal `checkin_pagi` — ini perlu dicek dengan data CSV/import asli sebelum rilis (lihat catatan asumsi di Implementation Decisions); kalau formatnya beda, tambah konversi format saat implementasi, bukan menebak di spec ini.
