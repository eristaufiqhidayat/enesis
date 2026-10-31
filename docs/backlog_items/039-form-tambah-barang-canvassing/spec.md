# Form Tambah Barang Canvassing (Muatan Canvassing dari Web)

## Problem Statement
Ikon "Tambah Barang Canvassing" di Topbar dan tombol tambah di `/barang_canvassing` membuka `/barang_canvassing/add` yang hanya halaman placeholder "Coming Soon" (`Route::inertia` ke `Menu/ComingSoon`). Padahal aplikasi HP sales membaca muatan dari `trx_canvassing` yang "disiapkan staf depo (dari web)" (lihat migration `2026_10_24_000004`), dan satu-satunya cara membuat muatan sekarang adalah command sementara `php artisan enesis:siapkan-canvassing` (`SiapkanCanvassing`). Staf depo tidak bisa menyiapkan muatan lewat UI.

## Solution
Form Tambah Barang Canvassing di `/barang_canvassing/add` untuk menyiapkan satu muatan: tanggal, depo, sales penerima, dan daftar produk beserta qty yang dibawa. Simpan membuat `trx_canvassing` (status `disiapkan`, `disiapkan_at` terisi, nomor otomatis `CNV{yyyymmdd}-{id 5 digit}`) dan `trx_canvassing_item` dalam satu transaksi. Muatan itu lalu muncul di aplikasi HP sales (terima barang) dan di tabel `/barang_canvassing`.

Tabel `/barang_canvassing` diperluas supaya menampilkan **semua status** (Disiapkan, Diterima, Dikembalikan, Selesai), bukan hanya yang sudah diterima, supaya muatan yang baru ditambah langsung terlihat. Status Disiapkan tampil sebagai badge. Menu laporan lain (Lap. Barang Canvassing, Pengembalian Barang, Lap. Barang Habis Terjual) tidak berubah.

## User Stories
1. Sebagai staf depo, saya ingin membuka form tambah dari ikon tambah di halaman Barang Canvassing, bukan halaman "Coming Soon".
2. Sebagai staf depo, saya ingin memilih depo lalu sales (Sales Motoris aktif dengan koordinator di depo itu), supaya muatan hanya bisa dibuat untuk penerima yang benar.
3. Sebagai staf depo, saya ingin menambah beberapa produk dengan qty bawa dalam satu form (produk tidak boleh dobel, qty > 0), supaya satu muatan lengkap dibuat sekali jalan.
4. Sebagai staf depo, saya ingin sistem menolak muatan kedua untuk sales yang sama di tanggal yang sama, karena aplikasi HP hanya membaca muatan terbaru hari itu.
5. Sebagai staf depo, setelah simpan saya ingin kembali ke daftar dan melihat muatan baru berstatus Disiapkan.
6. Sebagai admin dengan role bercakupan depo/koordinator, saya hanya bisa memilih depo dan sales dalam cakupan saya; membuat muatan di luar cakupan ditolak.
7. Sebagai pemilik produk, saya ingin hak akses `tambah` pada menu `barang_canvassing` menentukan siapa yang boleh membuka form dan menyimpan.

## Implementation Decisions
- Route (di grup auth, sebelum catch-all), menggantikan placeholder `Route::inertia('/barang_canvassing/add', ...)`: `GET /barang_canvassing/add` (halaman Inertia), `GET /barang_canvassing/opsi?depo=` (JSON: sales dan produk), `POST /barang_canvassing` (simpan). Semua `hak:barang_canvassing,tambah`. Memperluas `BarangCanvassingController` yang sudah ada (action `item` dari spec 038).
- Halaman Vue `Pages/Motoris/BarangCanvassingTambah.vue`, disusun dari komponen UI atomic (`Card`, `Input`, `Select`, `Button`, `FieldError`, `Alert`, `PageHeader`, `Breadcrumb`), pola form seperti `Pages/Karyawan/Form.vue`.
- Validasi: tanggal (default hari ini WIB, `config('mobile.zona_waktu')`); `depo_id` ada di `mk_depo` dan dalam cakupan role; `karyawan_id` kategori `sales`, aktif, `depo_id` sama, punya koordinator kategori `koordinator` (aturan yang sama dengan `SumberCanvassing::dasar`) dan dalam cakupan role (`Akses::batasiKaryawan`); `items` minimal satu, `produk_id` ada di `mk_produk` dan unik, `qty_bawa` numerik > 0.
- Satu muatan per sales per tanggal: tolak jika sudah ada `trx_canvassing` untuk `karyawan_id` + `tanggal` (pesan Indonesia menyebut nomor yang sudah ada).
- Simpan dalam `DB::transaction`: insert header dengan nomor sementara, update nomor `CNV{Ymd}-{id 5 digit}` (sama dengan `SiapkanCanvassing`), insert item. `sumber` = `web` (kolom `sumber` sudah ada; `SumberPenjualan::label` perlu mengembalikan "Web Enesis" untuk `web`). Setelah simpan redirect ke `/barang_canvassing` dengan flash sukses.
- `SumberCanvassingMuatan` tidak lagi menyaring `diterima_at`; syarat itu di `SumberCanvassing::dasar()` dijadikan parameter supaya tampilan per produk dan laporan lain tetap hanya yang sudah diterima. Urutan tabel muatan memakai `COALESCE(c.diterima_at, c.disiapkan_at)` desc. `ubahBaris` menambah label status `Disiapkan`; badge status (di `Menu/Show.vue`) mendapat warna untuk `Disiapkan`.
- Tidak ada migration baru (kolom `sumber` sudah ada, panjang 10 cukup untuk `web`).

## Testing Decisions
- Feature test (`tests/Feature/Admin/`, dasar `AdminTestCase`): simpan valid membuat header + item dengan status `disiapkan`, nomor berformat, `sumber` `web`; validasi menolak produk dobel, qty <= 0, sales beda depo, karyawan non-sales/tanpa koordinator, muatan kedua di tanggal sama; role tanpa hak `tambah` 403; role bercakupan depo tidak bisa membuat untuk depo lain; setelah simpan muatan tampil di `/barang_canvassing` dengan status Disiapkan.
- Regresi: `/lap_barang_canvassing`, `/pengembalian_barang`, `/lap_barang_habis_terjual` tetap hanya memuat muatan yang sudah diterima.
- Assert lewat response (`assertInertia`/`assertJson`) dan efek di tabel, bukan implementasi internal.

## Out of Scope
- Edit atau hapus/batalkan muatan yang sudah dibuat, dan edit qty setelah disiapkan.
- Ekspor, import massal (Excel/CSV) muatan.
- Perubahan alur terima/kembalikan di aplikasi HP.
- Perhitungan stok gudang atau validasi stok depo terhadap qty bawa.
- Template Barang Canvassing (menu `template_barang_canvassing`).
