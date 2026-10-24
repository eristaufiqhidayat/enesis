## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Halaman placeholder "Tambah Loading Barang" dan "Tambah Barang Canvassing" (ikon pintasan di kanan atas topbar) dirender lewat Inertia + `AppLayout.vue`, bukan Blade. Ini tracer bullet pertama untuk halaman di luar `MenuController`: membuktikan pola `Route::inertia`, layout, breadcrumb, dan tombol kembali bekerja tanpa full reload.

## Acceptance criteria

- [ ] `/loading_barang/add` dan `/barang_canvassing/add` merespons Inertia dengan judul dan tujuan tombol kembali yang sama seperti sekarang.
- [ ] Middleware `hak:<slug>,tambah` tetap berlaku: user tanpa hak tambah tidak bisa membuka halaman.
- [ ] Sidebar, topbar, dan breadcrumb tampil sama seperti di halaman menu tabel; tombol kembali menuju menu asal tanpa full reload.
- [ ] Ikon pintasan "Tambah Loading Barang" dan "Tambah Barang Canvassing" di topbar Vue berpindah tanpa full reload.
- [ ] View Blade `menu.coming_soon` dihapus.

## Implementation steps

- [ ] Ganti dua `Route::view(... 'menu.coming_soon' ...)` di `routes/web.php` jadi `Route::inertia` dengan props `title` dan `back`; middleware `hak` tetap.
- [ ] Buat page Vue `Menu/ComingSoon` memakai `AppLayout`, disusun dari komponen `ui` (ekstrak molecule Vue `PageHeader` padanan `page-header` Blade bila halaman butuh header).
- [ ] Ganti dua link pintasan "Tambah" di `Topbar.vue` dari `<a>` jadi `<Link>` Inertia.
- [ ] Hapus view Blade `menu/coming_soon`.
- [ ] Tambah/ubah feature test: `assertInertia` untuk komponen & props, serta tes 403 untuk user tanpa hak tambah; jalankan `php artisan test` dan `vendor/bin/pint`.

## Blocked by

- 01-navigasi-inertia-ke-blade-full-reload.md
