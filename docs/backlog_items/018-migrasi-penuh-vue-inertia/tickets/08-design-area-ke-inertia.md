## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Halaman Design Polygon Area (master area) dirender lewat Inertia + Vue dengan fitur yang sama seperti sekarang: pilih wilayah, gambar/simpan/muat/hapus polygon, pencarian alamat dan reverse geocoding (Nominatim). Memakai ulang composable peta dari tiket Tracking.

## Acceptance criteria

- [ ] Gambar, simpan, muat, dan hapus polygon area berfungsi sama, termasuk dropdown opsi wilayah dan pencarian alamat.
- [ ] Endpoint JSON `options`/`areas`/store/destroy tidak berubah dan tetap dipanggil dengan token CSRF.
- [ ] Tombol simpan/hapus mengikuti hak `master_area,ubah` / `master_area,hapus` (ditentukan server, diteruskan sebagai props).
- [ ] Pindah halaman lalu kembali tidak menggandakan instance peta atau layer gambar.
- [ ] Ikon pintasan "Design Polygon Area" di topbar Vue berpindah tanpa full reload.
- [ ] View Blade `area/design` dihapus.

## Implementation steps

- [ ] Ubah `DesignAreaController@index` ke `Inertia::render('Area/Design')` dengan props yang sekarang dikirim ke view, ditambah hak ubah/hapus.
- [ ] Buat page Vue `Area/Design` memakai `useLeafletMap`, memindahkan logika gambar polygon dan pemanggilan endpoint JSON jadi state reaktif Vue.
- [ ] Ganti link pintasan "Design Polygon Area" di `Topbar.vue` dari `<a>` jadi `<Link>` Inertia.
- [ ] Hapus view Blade `area/design`.
- [ ] Ubah `DesignAreaTest`: tes halaman memakai `assertInertia`, tes endpoint JSON tidak berubah. Jalankan `php artisan test` dan `vendor/bin/pint`.
- [ ] Verifikasi manual di browser: gambar/simpan/hapus polygon, pencarian alamat, dan bolak-balik antar menu tanpa error console.

## Blocked by

- 07-tracking-ke-inertia.md
