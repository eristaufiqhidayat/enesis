## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Peta Tracking History Koordinator Location dan Tracking History Sales Location dirender lewat satu halaman Inertia + Vue, dengan fitur peta yang sama seperti sekarang (pilih koordinator/sales, jumlah titik GPS, auto-pilih yang punya data, rute GPS). Karena Inertia tidak full reload, instance peta Leaflet harus dibuat dan dilepas mengikuti siklus hidup komponen; inisialisasi peta diekstrak ke composable yang dipakai ulang oleh tiket Design Area.

## Acceptance criteria

- [x] Kedua menu tracking tampil dan berfungsi sama seperti versi Blade.
- [x] Endpoint JSON `locations`/`counts` tidak berubah.
- [x] Pindah ke menu lain lalu kembali ke peta tidak memunculkan error "Map container is already initialized" di console dan tidak menggandakan listener/layer.
- [x] Middleware `hak:<slug>` tidak berubah.
- [x] View Blade `tracking/history` dihapus.

## Implementation steps

- [x] Buat composable Vue `useLeafletMap`: buat peta di `onMounted` pada elemen `ref`, pasang layer tile OSM/Esri, `map.remove()` di `onUnmounted`. Leaflet tetap dari `public/vendor/leaflet`.
- [x] Ubah `TrackingKoordinatorController@index` dan `TrackingSalesController@index` ke `Inertia::render('Tracking/History')` dengan props jenis tracking dan URL endpoint.
- [x] Buat page Vue `Tracking/History`, memindahkan logika vanilla JS peta jadi state reaktif Vue + `fetch` ke endpoint JSON yang sama.
- [x] Hapus view Blade `tracking/history`.
- [x] Ubah `TrackingKoordinatorTest` dan `TrackingSalesTest`: tes halaman memakai `assertInertia`, tes endpoint JSON tidak berubah. Jalankan `php artisan test` dan `vendor/bin/pint`.
- [x] Verifikasi manual di browser: peta, pemilihan, rute, dan bolak-balik antar menu tanpa error console.

## Blocked by

- 02-coming-soon-ke-inertia.md
