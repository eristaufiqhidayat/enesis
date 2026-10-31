# Endpoint ubah item muatan yang masih Disiapkan

## Parent

Spec: [docs/backlog_items/040-edit-item-muatan-disiapkan/spec.md](../spec.md)

## What to build

`PUT /barang_canvassing/{canvassing}/item` menggantikan daftar item sebuah muatan selama masih berstatus Disiapkan (belum diterima motoris), dengan validasi, cakupan role, dan penguncian terhadap penerimaan di aplikasi HP. Respons `GET /barang_canvassing/{id}/item` juga memberi tahu apakah muatan itu `dapat_diubah`.

## Acceptance criteria

- [x] Muatan Disiapkan: qty bisa diubah, produk bisa dihapus dan ditambah; hasil akhirnya persis daftar yang dikirim, tanpa duplikat, header muatan tidak berubah.
- [x] Ditolak 422: daftar kosong, produk dobel, produk tidak ada, qty ≤ 0.
- [x] Muatan berstatus diterima/dikembalikan/selesai ditolak 409 dan itemnya tidak berubah; muatan di luar cakupan role atau di luar aturan Koordinator → Motoris 404.
- [x] Role tanpa hak `ubah` mendapat 403; perubahan tercatat di riwayat admin.
- [x] `GET .../item` memuat `dapat_diubah` = true hanya bila status Disiapkan dan akun punya hak `ubah`.

## Implementation steps

- [x] Route `PUT /barang_canvassing/{canvassing}/item` (`hak:barang_canvassing,ubah`, `whereNumber`) sebelum catch-all di `routes/web.php`.
- [x] `BarangCanvassingController@ubahItem`: validasi sama dengan `store`, ambil muatan lewat `SumberCanvassing::dasar` (`termasukBelumDiterima`), transaksi dengan `lockForUpdate`, guard status, simpan berbasis selisih, `Akses::catat`.
- [x] Tambah `dapat_diubah` ke respons `item`.
- [x] Feature test sesuai spec (ubah/hapus/tambah, tiap penolakan, hak, cakupan, riwayat); `php artisan test` dan `vendor/bin/pint`.

## Blocked by

None
