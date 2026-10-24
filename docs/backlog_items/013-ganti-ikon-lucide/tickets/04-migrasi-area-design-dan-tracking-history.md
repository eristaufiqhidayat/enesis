## Parent

docs/backlog_items/013-ganti-ikon-lucide/spec.md

## What to build

Pindahkan ikon statis di `resources/views/area/design.blade.php` dan `resources/views/tracking/history.blade.php` ke Icon atom, dan ganti ikon yang muncul di string HTML yang dibangun lewat JS inline di kedua file itu (popup Leaflet, tombol layer peta, tombol hapus area, kontrol lain) dengan markup `<svg>` Lucide yang sepadan disisipkan langsung di template literal JS (bukan Icon atom, karena bukan konteks render Blade).

- Markup Blade statis (label "Cari Alamat di Peta", tombol Tampilkan/Reset/Mulai Gambar/Selesai/Hapus Titik Terakhir/Batal, header "Gambar Polygon Baru" di `area/design.blade.php`; label koordinator/depo/telepon di `tracking/history.blade.php`) → Icon atom.
- String HTML di JS inline (tombol hapus area di popup Leaflet, tombol layer Satelit/2D Map/Garis Jalan, link "Lihat di Google Map" di `tracking/history.blade.php`) → markup SVG Lucide ditulis langsung di template literal JS, dengan nama ikon yang sepadan (`trash`, `satellite` kalau tersedia atau alternatif representatif, `map`, `road` kalau tersedia atau alternatif, `map-pin`/`external-link`, dst. — ditentukan saat implementasi).

## Acceptance criteria

- [x] `area/design.blade.php` dan `tracking/history.blade.php` tidak lagi mengandung `fas fa-*` di markup Blade statis maupun di string JS inline.
- [x] Markup Blade statis di kedua file memanggil Icon atom.
- [x] String HTML yang dibangun di JS inline (popup Leaflet, tombol layer peta, tombol hapus area, link Google Maps) menyisipkan SVG Lucide yang valid (bukan Font Awesome).
- [x] Verifikasi manual: halaman Design Polygon Area (tombol gambar/undo/cancel/hapus titik, popup hapus area) dan Tracking Koordinator (popup koordinator, tombol ganti layer peta, link Google Maps) tampil dengan ikon Lucide dan tetap berfungsi seperti sebelumnya (klik tombol tetap memicu aksi yang sama).

## Blocked by

- 01-icon-atom-lucide-svg.md
