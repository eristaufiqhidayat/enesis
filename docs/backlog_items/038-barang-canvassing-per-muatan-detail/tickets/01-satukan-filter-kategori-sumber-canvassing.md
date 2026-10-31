# Prefactoring: satukan aturan filter kategori di SumberCanvassing

## Parent

Spec: [docs/backlog_items/038-barang-canvassing-per-muatan-detail/spec.md](../spec.md)

## What to build

Aturan "hanya canvassing dari Sales Koordinator ke Sales Motoris" (`k.kategori = 'sales'`, `ko.kategori = 'koordinator'`, `whereNotNull('c.diterima_at')`) saat ini hanya ada di satu tempat: `SumberCanvassing::query()`. Tiket berikutnya (tabel muatan di tiket 02, drawer detail di tiket 03) butuh query baru yang harus memakai cakupan data yang sama persis — supaya tabel muatan dan drawer detailnya tidak pernah menampilkan data yang berbeda. Tarik join + filter itu jadi satu method statis yang bisa dipanggil ulang, tanpa mengubah perilaku `/lap_barang_canvassing`, `/pengembalian_barang`, `/lap_barang_habis_terjual` yang sudah ada.

## Acceptance criteria

- [x] Join `trx_canvassing_item` + `trx_canvassing` + `mk_karyawan` (k, ko) beserta filter kategori dan `whereNotNull('c.diterima_at')` ada di satu method statis yang bisa dipakai ulang di luar `SumberCanvassing::query()`.
- [x] `SumberCanvassing::query()` memakai method itu; perilaku `/barang_canvassing` (saat ini masih memakai `lap_barang_canvassing`), `/lap_barang_canvassing`, `/pengembalian_barang`, `/lap_barang_habis_terjual` tidak berubah.
- [x] Test yang sudah ada (`tests/Feature/Admin/MenuStokCanvassingMotorisTest.php`, termasuk kasus `CNV-4` kategori non-sales) tetap lolos.

## Implementation steps

- [x] Ekstrak join dasar (`trx_canvassing_item` → `trx_canvassing` → `mk_karyawan as k` → `mk_karyawan as ko` → filter kategori → `whereNotNull('c.diterima_at')`) dari `SumberCanvassing::query()` ke method statis baru di kelas yang sama (`SumberCanvassing::dasar()`).
- [x] Ubah `SumberCanvassing::query()` untuk memanggil method statis itu lalu menambah join/`select` yang spesifik untuk tampilan per produk.
- [x] Jalankan `php artisan test --filter=MenuStokCanvassingMotorisTest` dan `vendor/bin/pint` untuk memastikan tidak ada regresi.

## Blocked by

None (can start immediately)
