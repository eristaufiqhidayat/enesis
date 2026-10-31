# Migration dan endpoint batalkan muatan Disiapkan

## Parent

Spec: [docs/backlog_items/041-batalkan-muatan-disiapkan/spec.md](../spec.md)

## What to build

Kolom `dibatalkan_at` dan `dibatalkan_oleh` pada `trx_canvassing`, endpoint `DELETE /barang_canvassing/{id}` yang membatalkan (soft) muatan yang masih Disiapkan, dan penyaringan muatan yang dibatalkan di web, aplikasi HP, serta aturan satu muatan per motoris per tanggal.

## Acceptance criteria

- [x] Muatan Disiapkan dibatalkan: `dibatalkan_at`, `dibatalkan_oleh`, dan `status = 'dibatalkan'` terisi, item tetap ada.
- [x] Pelaku tersimpan sebagai ID: `dibatalkan_oleh` = `id` akun yang login (bukan dari input request; tidak ada kolom nama), dan pembatalan tercatat di riwayat admin (aksi `hapus`, objek `barang_canvassing`).
- [x] Muatan yang dibatalkan tidak tampil di `/barang_canvassing`, drawer `item` dan `ubahItem` menjawab 404, dan tidak bisa dibatalkan lagi (409/404).
- [x] Muatan berstatus diterima/dikembalikan/selesai ditolak 409 dan datanya tidak berubah; di luar cakupan role atau di luar aturan Koordinator → Motoris 404; role tanpa hak `hapus` 403.
- [x] Aplikasi HP: muatan yang dibatalkan tidak menjadi muatan hari ini, dan menerima nomornya menghasilkan 404.
- [x] Setelah dibatalkan, muatan baru untuk motoris dan tanggal yang sama bisa dibuat.
- [x] `GET .../item` memuat `dapat_dibatalkan` = true hanya bila status Disiapkan dan akun punya hak `hapus`.

## Implementation steps

- [x] Migration `dibatalkan_at` (timestamp nullable) dan `dibatalkan_oleh` (unsignedBigInteger nullable) pada `trx_canvassing`.
- [x] Route `DELETE /barang_canvassing/{canvassing}` (`hak:barang_canvassing,hapus`, `whereNumber`) sebelum catch-all di `routes/web.php`; `BarangCanvassingController@batalkan` (cakupan lewat `dasar`, transaksi + `lockForUpdate`, guard status, `Akses::catat`).
- [x] `SumberCanvassing::dasar` menyaring `whereNull('c.dibatalkan_at')`; `store` tidak menghitung muatan dibatalkan pada aturan satu muatan per motoris per tanggal; tambah `dapat_dibatalkan` ke respons `item`; label status `Dibatalkan` di `SumberCanvassingMuatan::ubahBaris`.
- [x] `HariKerja::canvassing()` dan `Mobile\CanvassingController::milik()` mengabaikan muatan yang dibatalkan.
- [x] Feature test sesuai spec (web dan API mobile); `php artisan test` dan `vendor/bin/pint`.

## Blocked by

None
