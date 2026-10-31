# Tabel muatan (header-level) di halaman /barang_canvassing

## Parent

Spec: [docs/backlog_items/038-barang-canvassing-per-muatan-detail/spec.md](../spec.md)

## What to build

Halaman `/barang_canvassing` berhenti memakai data `lap_barang_canvassing` (satu baris per produk) dan menampilkan satu baris per **muatan** canvassing (`trx_canvassing`): nomor, depo, sales penerima, koordinator, status, waktu diterima/dikembalikan, jumlah SKU, dan total qty dibawa/diterima. Pencarian dan paginasi tetap ada, konsisten dengan halaman data lain di aplikasi ini. Drawer detail per produk belum ada di tiket ini (itu tiket 03) — baris muatan boleh tampil tanpa aksi klik dulu.

**Catatan implementasi**: ternyata tidak perlu controller/route/halaman Vue baru — cukup tabel config baru dengan kelas `SumberTabel` sendiri, lewat jalur generik `MenuController`/`Menu/Show.vue` yang sudah ada (lihat `CLAUDE.md` § config-driven menus). Lihat juga spec yang sudah diperbarui.

## Acceptance criteria

- [x] `GET /barang_canvassing` menampilkan satu baris per nomor muatan (bukan per produk), dengan kolom ringkasan (jumlah SKU, total qty bawa, total qty diterima) yang benar.
- [x] Muatan yang penerimanya bukan Sales Motoris dari Sales Koordinator (kategori selain `sales`/`koordinator`, sesuai aturan dari tiket 01) tidak muncul di tabel.
- [x] Pencarian (nomor, nama sales, nama koordinator, nama depo) dan paginasi 50 baris berfungsi (didapat gratis dari `MenuController@show`, tidak perlu kode tambahan).
- [x] `/lap_barang_canvassing`, `/pengembalian_barang`, `/lap_barang_habis_terjual` tidak berubah perilakunya.

## Implementation steps

- [x] Tambah tabel config baru `barang_canvassing` di `config/marketama.php` (`tables.barang_canvassing`), dengan `sumber` kelas baru dan kolom header (ID, Canvassing No, Warehouse, Salesperson, Koordinator, Status, Receive/Return Date & Datetime, Jumlah SKU, Total Qty Bawa, Total Qty Diterima, Sumber).
- [x] Ubah `pages.barang_canvassing.tables` dari `['lap_barang_canvassing']` ke `['barang_canvassing']`, dan `same_as` dari `'lap_barang_canvassing'` ke `null`.
- [x] Buat `App\Support\Menu\Motoris\SumberCanvassingMuatan` (implements `SumberTabel`): `query()` memakai `SumberCanvassing::dasar($user)` (dari tiket 01) lalu `GROUP BY c.id` dengan agregat `COUNT`/`SUM`; `ubahBaris()` format tanggal/jam WIB, label status, format angka; `kolomCari()` sama dengan `SumberCanvassing`.
- [x] Perluas feature test yang sudah ada (`tests/Feature/Admin/MenuStokCanvassingMotorisTest.php`) untuk memverifikasi `/barang_canvassing` menampilkan satu baris per muatan dengan ringkasan benar dan muatan `CNV-4` (non-sales/tanpa koordinator) tersaring, dan `/lap_barang_canvassing` tetap tabel rata per produk apa adanya.
- [x] Jalankan `php artisan test` (seluruh suite) dan `vendor/bin/pint` — lolos (4 kegagalan pre-existing di `AutoGridTest`/`GenerateKelurahanTest`, tidak terkait, karena SSR Inertia di environment test).

## Blocked by

- `01-satukan-filter-kategori-sumber-canvassing` (selesai)
