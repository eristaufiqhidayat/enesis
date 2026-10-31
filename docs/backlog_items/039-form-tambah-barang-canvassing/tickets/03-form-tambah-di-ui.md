# Halaman form Tambah Barang Canvassing

## Parent

Spec: [docs/backlog_items/039-form-tambah-barang-canvassing/spec.md](../spec.md)

## What to build

Ganti placeholder `/barang_canvassing/add` dengan form: tanggal, depo, sales (terisi dari opsi per depo), dan daftar baris produk + qty bawa (tambah/hapus baris). Simpan lewat endpoint tiket 02; kesalahan validasi tampil per field. Setelah sukses kembali ke daftar.

## Acceptance criteria

- [x] Ikon tambah di Topbar dan halaman Barang Canvassing membuka form (bukan Coming Soon); halaman hanya bisa dibuka dengan hak `tambah`.
- [x] Memilih depo memuat sales dan produk; baris produk bisa ditambah/dihapus, produk yang sudah dipilih tidak muncul lagi di baris lain.
- [x] Simpan sukses mengembalikan ke `/barang_canvassing` dan muatan baru terlihat berstatus Disiapkan; error validasi tampil di field terkait.
- [x] Tampilan benar di lebar sempit.

## Implementation steps

- [x] `GET /barang_canvassing/add` merender halaman Inertia baru (ganti `Route::inertia` ke `Menu/ComingSoon`).
- [x] Buat `Pages/Motoris/BarangCanvassingTambah.vue` dari komponen `ui` yang ada (ekstrak molecule bila >=2 tempat memakai pola yang sama), styling lewat class komponen `app.css`.
- [x] Tes feature untuk halaman `add` (hak, props); verifikasi manual di browser (alur lengkap, lebar sempit); `php artisan test` dan `vendor/bin/pint`.

## Blocked by

- `02-endpoint-simpan-muatan`
