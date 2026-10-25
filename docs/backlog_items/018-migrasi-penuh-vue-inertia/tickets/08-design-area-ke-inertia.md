## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Halaman Design Polygon Area (master area) dirender lewat Inertia + Vue dengan fitur yang sama seperti sekarang: pilih wilayah, gambar/simpan/muat/hapus polygon, pencarian alamat dan reverse geocoding (Nominatim). Memakai ulang composable peta dari tiket Tracking.

## Acceptance criteria

- [x] Gambar, simpan, muat, dan hapus polygon area berfungsi sama, termasuk dropdown opsi wilayah dan pencarian alamat.
- [x] Endpoint JSON `options`/`areas`/store/destroy tidak berubah dan tetap dipanggil dengan token CSRF.
- [x] Tombol simpan/hapus mengikuti hak `master_area,ubah` / `master_area,hapus` (ditentukan server, diteruskan sebagai props).
- [x] Pindah halaman lalu kembali tidak menggandakan instance peta atau layer gambar.
- [x] Ikon pintasan "Design Polygon Area" di topbar Vue berpindah tanpa full reload.
- [x] View Blade `area/design` dihapus.

## Implementation steps

- [x] Ubah `DesignAreaController@index` ke `Inertia::render('Area/Design')` dengan props yang sekarang dikirim ke view, ditambah hak ubah/hapus.
- [x] Buat page Vue `Area/Design` memakai `useLeafletMap`, memindahkan logika gambar polygon dan pemanggilan endpoint JSON jadi state reaktif Vue.
- [x] Ganti link pintasan "Design Polygon Area" di `Topbar.vue` dari `<a>` jadi `<Link>` Inertia.
- [x] Hapus view Blade `area/design`.
- [x] Ubah `DesignAreaTest`: tes halaman memakai `assertInertia`, tes endpoint JSON tidak berubah. Jalankan `php artisan test` dan `vendor/bin/pint`. (`php artisan test` 200 tes hijau; `BreadcrumbTest` untuk design area juga diubah ke `assertInertia`; `vendor/bin/pint` lulus.)
- [x] Verifikasi manual di browser: gambar/simpan/hapus polygon, pencarian alamat, dan bolak-balik antar menu tanpa error console. Dijalankan di Chrome pada `localhost:8001`: simpan (201) dan hapus (204) area uji, kaskade dropdown wilayah, filter daftar, navigasi Inertia ke Loading Barang lalu kembali (satu kontainer peta, tanpa full reload), dan tidak ada error console.

## Blocked by

- 07-tracking-ke-inertia.md
