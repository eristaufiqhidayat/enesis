## Parent

docs/backlog_items/014-sidebar-header-kontras-flyout-submenu/spec.md

## What to build

Judul grup (`section-label`) di sidebar harus terbaca jelas di mode lebar penuh (saat ini warnanya terlalu redup, nyaris menyatu dengan background gradient biru sidebar). Selain itu, saat sidebar dipersempit jadi mode ikon-saja (collapsed), ikon tiap grup harus tetap tampil sebagai pemisah visual antar grup — bukan ikut hilang total seperti sekarang.

## Acceptance criteria

- [x] Warna teks judul grup (dan ikonnya) di mode lebar penuh dinaikkan kontrasnya secara jelas dibanding kondisi sekarang, tanpa membuatnya terlihat sama persis dengan item menu biasa (hierarki "judul grup" vs "menu" tetap terasa beda).
- [x] Saat sidebar dalam mode collapsed (icon-only) dan tidak di-hover, ikon grup tetap tampil di antara ikon-ikon menu sebagai pemisah; judul teks grup dan chevron-nya tetap tersembunyi seperti sekarang.
- [x] Hover ikon grup di mode collapsed menampilkan nama grup lewat tooltip native (`title`).
- [ ] Perubahan berlaku identik di kedua stack rendering (halaman Blade dan halaman Inertia/Vue), karena sumber perubahan ada di file CSS yang dipakai bersama.
- [x] Mekanisme collapse per grup dan collapse-all yang sudah ada (klik judul grup untuk expand/collapse daftar menunya) tidak berubah perilakunya.

## Blocked by

None (can start immediately).
