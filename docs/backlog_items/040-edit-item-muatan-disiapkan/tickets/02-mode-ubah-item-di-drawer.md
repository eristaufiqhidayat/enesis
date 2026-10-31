# Mode ubah item di drawer detail muatan

## Parent

Spec: [docs/backlog_items/040-edit-item-muatan-disiapkan/spec.md](../spec.md)

## What to build

Di drawer detail muatan (Daftar Muatan Motoris), muatan berstatus Disiapkan mendapat tombol "Ubah item" yang membuka mode edit: ubah qty, hapus produk, tambah produk, lalu Simpan atau Batal. Muatan lain tetap hanya-baca.

## Acceptance criteria

- [x] Tombol "Ubah item" hanya muncul bila `dapat_diubah` (status Disiapkan dan akun punya hak `ubah`).
- [x] Mode edit: qty bisa diubah, produk bisa dihapus, produk baru bisa ditambah lewat Combobox (produk yang sudah ada tidak ditawarkan lagi); Batal membuang perubahan.
- [x] Simpan sukses: toast sukses, drawer kembali ke mode lihat dengan data terbaru, dan baris di tabel daftar ikut diperbarui.
- [x] Galat validasi tampil di baris terkait; bila muatan ternyata sudah diterima (409), pesan tampil dan drawer memuat ulang isi terbaru dalam mode hanya-baca.
- [ ] Tampilan benar di lebar sempit. _(belum diverifikasi di browser)_

## Implementation steps

- [x] Perluas `DetailMuatanCanvassing.vue` dengan mode edit memakai pola langkah 2 wizard (Combobox produk, input qty, tabel item); ekstrak bagian yang dipakai di dua tempat (daftar produk tersisa, validasi qty) ke komponen/helper bersama bila ≥2 tempat memakainya.
- [x] Muat opsi produk dari `GET /barang_canvassing/opsi?depo=<depo muatan>`; kirim `PUT .../item`; perbarui drawer dan tabel setelah sukses.
- [ ] Vitest untuk logika murni yang dipisahkan; verifikasi manual di browser (alur lengkap, status berubah saat mengedit, lebar sempit); `php artisan test`, `npm test`, dan `vendor/bin/pint`. _(Vitest `BarisTambahProduk`, tes PHP, pint, dan alur lengkap + 409 di browser sudah; lebar sempit belum.)_

## Blocked by

- `01-endpoint-ubah-item-muatan`
