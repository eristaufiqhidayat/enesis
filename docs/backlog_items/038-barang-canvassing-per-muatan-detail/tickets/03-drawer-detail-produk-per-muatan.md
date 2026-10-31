# Drawer detail produk per muatan canvassing

## Parent

Spec: [docs/backlog_items/038-barang-canvassing-per-muatan-detail/spec.md](../spec.md)

## What to build

Klik baris muatan di tabel `/barang_canvassing` (tiket 02) membuka drawer dari kanan (pola `Modal placement="kanan"` yang sudah dipakai di drawer detail depo/area) berisi daftar produk muatan itu: nama & SKU produk, kategori/brand/satuan, qty dibawa, qty diterima, qty terjual, sisa, qty kembali, status (Habis Terjual/Belum Habis) — kolom yang sama seperti yang sekarang ada di `lap_barang_canvassing`. Drawer tertutup lewat tombol tutup atau Esc.

## Acceptance criteria

- [x] Klik baris muatan di tabel membuka drawer berisi daftar produk muatan tersebut dengan kolom qty bawa/terima/terjual/sisa/kembali dan status yang benar. (via tautan Canvassing No di baris; isi diverifikasi test)
- [x] Endpoint detail produk menolak/mengembalikan kosong untuk muatan di luar cakupan akses role (bukan lewat ID yang tidak termasuk hasil query header).
- [ ] Drawer tertutup lewat tombol tutup dan tombol Esc.

## Implementation steps

- [x] Tambah route `GET /barang_canvassing/{canvassing}/item` (JSON) di `routes/web.php`.
- [x] Tambah `BarangCanvassingController@item`: pakai `SumberCanvassing::query($user)->where('c.id', $canvassing)` (method dari tiket 01) untuk daftar produk, kembalikan 404 kalau muatan tidak ada dalam cakupan akses user.
- [x] Buat komponen `resources/js/Pages/Motoris/partials/DetailMuatanCanvassing.vue` memakai molecule `Modal placement="kanan"`, memuat data lewat fetch ke endpoint item saat dibuka. (dibuat di `Pages/Menu/partials/DetailMuatanCanvassing.vue`, karena halaman ini lewat `Menu/Show.vue`)
- [x] Wire klik baris di `Pages/Motoris/BarangCanvassing.vue` (tiket 02) untuk membuka drawer dengan `canvassing_id` baris itu. (di `Pages/Menu/Show.vue`, bukan `Motoris/BarangCanvassing.vue` yang tidak ada)
- [x] Buat feature test endpoint item: data produk benar untuk muatan yang valid, dan 404/kosong untuk muatan di luar cakupan akses (mis. depo lain saat role `cakupan: depo`).
- [ ] Verifikasi manual di browser: klik baris membuka drawer dari kanan, isi benar, Esc dan tombol tutup berfungsi, tampilan di lebar sempit.
- [x] Jalankan `php artisan test` dan `vendor/bin/pint`. (4 kegagalan lama AutoGrid/GenerateKelurahan, tidak terkait)

## Blocked by

- `02-tabel-muatan-halaman-barang-canvassing`
