# Endpoint opsi dan simpan muatan canvassing

## Parent

Spec: [docs/backlog_items/039-form-tambah-barang-canvassing/spec.md](../spec.md)

## What to build

Backend tambah muatan: `GET /barang_canvassing/opsi?depo=` (sales dan produk yang boleh dipilih) dan `POST /barang_canvassing` yang memvalidasi lalu membuat `trx_canvassing` + `trx_canvassing_item` status `disiapkan`, dengan nomor otomatis dan `sumber` `web`.

## Acceptance criteria

- [ ] Simpan valid membuat header + item dalam satu transaksi, nomor `CNV{Ymd}-{id 5 digit}`, `disiapkan_at` terisi, `sumber` `web`, lalu redirect ke `/barang_canvassing` dengan flash sukses.
- [ ] Ditolak: produk dobel, qty <= 0, tanpa produk, sales beda depo / bukan sales / tanpa koordinator, muatan kedua untuk sales yang sama di tanggal yang sama.
- [ ] Role tanpa hak `tambah` mendapat 403; role bercakupan depo/koordinator tidak bisa membuat di luar cakupannya.
- [ ] Label sumber "Web Enesis" tampil di kolom Sumber.

## Implementation steps

- [ ] Route `GET /barang_canvassing/opsi` dan `POST /barang_canvassing` (`hak:barang_canvassing,tambah`) sebelum catch-all di `routes/web.php`.
- [ ] `BarangCanvassingController@opsi` dan `@store` (validasi, cakupan lewat `Akses::batasiKaryawan`, transaksi, nomor).
- [ ] `SumberPenjualan::label()` mengenali `web`.
- [ ] Feature test sesuai spec (valid, tiap penolakan, hak, cakupan); `php artisan test` dan `vendor/bin/pint`.

## Blocked by

- `01-tabel-muatan-semua-status`
