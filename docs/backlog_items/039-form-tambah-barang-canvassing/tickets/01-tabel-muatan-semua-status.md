# Tabel muatan menampilkan semua status (termasuk Disiapkan)

## Parent

Spec: [docs/backlog_items/039-form-tambah-barang-canvassing/spec.md](../spec.md)

## What to build

`/barang_canvassing` menampilkan muatan dari semua status, termasuk yang baru disiapkan dan belum diterima sales, dengan badge status Disiapkan. Laporan lain (Lap. Barang Canvassing, Pengembalian Barang, Lap. Barang Habis Terjual) tetap hanya memuat muatan yang sudah diterima.

## Acceptance criteria

- [x] Muatan berstatus `disiapkan` (tanpa `diterima_at`) tampil di `/barang_canvassing` dengan badge Disiapkan; muatan non-sales/tanpa koordinator tetap tersaring.
- [x] Drawer detail produk (`/barang_canvassing/{id}/item`) bisa membuka muatan Disiapkan.
- [x] `/lap_barang_canvassing`, `/pengembalian_barang`, `/lap_barang_habis_terjual` tidak berubah (hanya muatan yang sudah diterima).

## Implementation steps

- [x] Ubah `SumberCanvassing::dasar()` agar syarat `diterima_at` bisa dimatikan lewat parameter; tampilan per produk tetap memakainya, `SumberCanvassingMuatan` tidak.
- [x] Urutkan tabel muatan dengan `COALESCE(c.diterima_at, c.disiapkan_at)` desc; tambah label status `Disiapkan` di `ubahBaris` dan warna badge di `warnaBadge` (`Menu/Show.vue`).
- [x] Sesuaikan `BarangCanvassingController@item` agar tidak menyaring `diterima_at`.
- [x] Perluas `MenuStokCanvassingMotorisTest` (CNV-3 berstatus disiapkan kini muncul di `/barang_canvassing`, tetap tidak muncul di laporan lain); jalankan `php artisan test` dan `vendor/bin/pint`.

## Blocked by

None
