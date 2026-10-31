# Barang Canvassing per Muatan, Klik untuk Detail Produk

## Problem Statement

Halaman `/barang_canvassing` saat ini (`config/marketama.php` → `pages.barang_canvassing`, `same_as: lap_barang_canvassing`) menampilkan data dari `SumberCanvassing` sebagai tabel rata: satu baris per **produk** dalam satu muatan canvassing (join `trx_canvassing` + `trx_canvassing_item`). Kalau satu muatan punya 10 produk, muatan itu muncul sebagai 10 baris dengan nomor, sales, koordinator, dan tanggal yang berulang — user harus menyisir banyak baris untuk melihat satu muatan secara utuh, dan tidak ada ringkasan per muatan (jumlah SKU, total qty dibawa/diterima/terjual/sisa).

## Solution

Halaman `/barang_canvassing` diubah jadi tampilan dua tingkat:

1. **Tabel utama** — satu baris per **muatan** (`trx_canvassing`): nomor, tanggal, depo, sales penerima, koordinator, status, waktu diterima/dikembalikan, plus ringkasan (jumlah SKU, total qty dibawa, total qty diterima). Sumber data tetap dibatasi aturan yang sudah diperbaiki di `SumberCanvassing` (hanya penerima kategori `sales` dengan koordinator kategori `koordinator`, dan `diterima_at` terisi).
2. **Klik baris** membuka drawer dari kanan (pola `Modal placement="kanan"` yang sudah dipakai di spec 032/033) berisi detail produk muatan itu (`trx_canvassing_item`): nama & SKU produk, kategori/brand/satuan, qty dibawa, qty diterima, qty terjual, sisa, qty kembali, status (Habis Terjual/Belum Habis), sama seperti kolom yang sekarang ada di `lap_barang_canvassing`.

Laporan lama **tidak dihapus**: menu "Lap. Barang Canvassing" (`/lap_barang_canvassing`) tetap menampilkan tabel rata per produk apa adanya, untuk siapa pun yang masih butuh ekspor/baca per baris produk. Begitu juga "Pengembalian Barang" dan "Lap. Barang Habis Terjual" yang memakai `SumberPengembalianBarang`/`SumberBarangHabisTerjual` — tidak berubah.

## User Stories

1. Sebagai koordinator gudang, saya ingin melihat daftar muatan canvassing (bukan daftar produk) supaya saya bisa memindai berapa banyak muatan yang sudah diterima/dikembalikan hari ini dalam satu layar.
2. Sebagai koordinator gudang, saya ingin tahu ringkasan tiap muatan (jumlah SKU, total qty dibawa vs diterima) tanpa membuka detail, supaya saya bisa mendeteksi muatan dengan selisih besar lebih cepat.
3. Sebagai koordinator gudang, saya ingin klik satu muatan untuk melihat rincian tiap produknya (qty bawa/terima/terjual/sisa/kembali), supaya saya bisa menindaklanjuti produk tertentu yang bermasalah.
4. Sebagai admin, saya ingin halaman ini tetap mencari (nomor, nama sales, nama koordinator, nama depo) dan terpaginasi, konsisten dengan halaman data lain di aplikasi ini.
5. Sebagai admin, saya ingin drawer detail tertutup lewat tombol tutup atau Esc, konsisten dengan drawer detail depo/area yang sudah ada.
6. Sebagai admin, saya ingin baris muatan yang penerimanya bukan Sales Motoris dari Sales Koordinator (sesuai aturan yang sudah diperbaiki) tetap tidak muncul di tabel utama maupun di drawer detailnya.
7. Sebagai product owner, saya ingin menu "Lap. Barang Canvassing" tetap menampilkan tabel rata per produk seperti sebelumnya, supaya alur kerja yang sudah ada untuk laporan itu tidak berubah.

## Implementation Decisions

- **Tabel muatan (header-level)**: dibangun lewat jalur konfigurasi yang sudah ada, bukan controller/route/halaman Vue baru — konsisten dengan arsitektur "config-driven menu" (`CLAUDE.md`). `pages.barang_canvassing` di `config/marketama.php` tetap memakai slug/route catch-all `/{slug}` + `MenuController@show` + `Menu/Show.vue`, hanya `tables` dan `same_as`-nya diubah dari `lap_barang_canvassing` ke tabel config baru `barang_canvassing` (sumber `App\Support\Menu\Motoris\SumberCanvassingMuatan`).
  - `SumberCanvassingMuatan::query()` memakai `SumberCanvassing::dasar($user)` (method statis bersama, lihat `app/Support/Menu/Motoris/SumberCanvassing.php`) lalu `GROUP BY c.id` dengan `COUNT(ci.id) AS jumlah_sku`, `SUM(ci.qty_bawa) AS total_qty_bawa`, `SUM(COALESCE(ci.qty_diterima, ci.qty_bawa)) AS total_qty_diterima` — jadi cakupan datanya (hanya Sales Koordinator → Sales Motoris, `diterima_at` terisi) otomatis sama dengan tampilan per produk, tanpa duplikasi filter.
  - Pencarian (`?q=`) dan paginasi 50 baris otomatis didapat dari `MenuController@show` yang sudah generik, memakai `kolomCari()` (`c.nomor`, `k.nama`, `ko.nama`, `d.nama`).
- **Drawer detail produk (tiket 03)**: generik `Menu/Show.vue`/`MenuController` tidak punya mekanisme fetch data tambahan per baris saat diklik (beda dari drawer detail depo/area di spec 032/033 yang datanya sudah ada di baris itu sendiri) — jadi klik baris untuk detail produk baru dikerjakan di tiket 03, lewat endpoint kecil baru `GET /barang_canvassing/{canvassing}/item` (JSON, didaftarkan sebelum catch-all) yang memanggil `SumberCanvassing::query($user)->where('c.id', $canvassing)`, plus komponen drawer di frontend.
- Tidak ada migration baru — memakai tabel yang sudah ada (`trx_canvassing`, `trx_canvassing_item`, `mk_karyawan`, `mk_depo`, `mk_produk`).

## Testing Decisions

- `tests/Feature/Admin/MenuStokCanvassingMotorisTest.php` (sudah ada) diperluas: `GET /barang_canvassing` diverifikasi lewat `assertInertia` + `loadDeferredProps` menampilkan satu baris per nomor muatan dengan ringkasan jumlah SKU/total qty benar, dan muatan non-sales/tanpa koordinator (`CNV-4`) tersaring. `GET /lap_barang_canvassing` diverifikasi tetap tabel rata per produk apa adanya (regresi langsung ketahuan di test yang sama).
- Tiket 03 menambah feature test untuk endpoint `GET /barang_canvassing/{id}/item`: data produk benar untuk muatan valid, dan 404/kosong untuk muatan di luar cakupan akses role.
- Assert lewat response (`assertInertia`/`assertJson`), bukan query ulang ke database.

## Out of Scope

- Mengubah data atau alur `trx_canvassing`/`trx_canvassing_item` (form Tambah Barang Canvassing, approve/terima/kembali) — itu bagian "Fase 5: alur tulis" di `docs/PRD.md`, bukan spec ini.
- Mengubah menu "Lap. Barang Canvassing", "Pengembalian Barang", "Lap. Barang Habis Terjual" — tetap tabel rata per produk seperti sekarang.
- Ekspor/print drawer detail.
- Filter/sort per kolom di drawer detail (cukup di tabel muatan utama, konsisten dengan `DataTable` yang sudah punya filter/sort per kolom dari spec 023).

## Further Notes

- `trx_canvassing`/`trx_canvassing_item` berisi data transaksi sales (bukan data pribadi identitas, tapi tetap data operasional HP) — sudah ada di repo lewat migration `2026_10_24_000004_create_mobile_api_tables.php`, tidak lewat CSV seeder, jadi tidak ada data contoh di `database/seeders/data` untuk diverifikasi manual; andalkan feature test dengan data rekaan.
- Spec ini bergantung pada perbaikan aturan "hanya Sales Koordinator ke Sales Motoris" yang sudah diterapkan di `SumberCanvassing::query()` (lihat percakapan sebelumnya) — query header baru harus memakai filter kategori yang sama agar dua tampilan (muatan & detail produk) tidak pernah berbeda cakupan datanya.
