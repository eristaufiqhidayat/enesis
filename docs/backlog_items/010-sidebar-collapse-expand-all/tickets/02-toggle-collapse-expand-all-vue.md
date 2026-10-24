## Parent

docs/backlog_items/010-sidebar-collapse-expand-all/spec.md

## What to build

Tambahkan tombol toggle collapse/expand semua grup yang sama (perilaku identik dengan ticket 01) di stack Vue/Inertia (`Sidebar.vue`), reuse state `collapsedGroups` dan `localStorage` key `sidebar-collapsed-groups` yang sudah ada dari ticket 009 — agar preferensi tetap konsisten saat user pindah antara halaman Blade dan halaman Inertia dalam sesi yang sama.

- Tombol ditaruh di `.brand`, bersebelahan dengan tombol collapse-sidebar-penuh (`data-sidebar-toggle`) yang sudah ada di template Vue.
- Tambah computed yang menentukan apakah semua grup berlabel pada menu saat ini (`page.props.menu`) sedang collapsed, untuk menentukan arah aksi tombol berikutnya (expand all vs collapse all) — sama seperti logika Blade di ticket 01.
- Fungsi toggle: set `collapsedGroups` jadi array semua label grup berlabel (collapse all) atau array kosong (expand all), lalu tulis ke `localStorage` lewat helper yang sudah ada (`writeCollapsedGroups`).
- Grup yang memuat menu aktif tetap tampil terbuka secara visual (reuse `isGroupOpen`/`groupContainsCurrentSlug` yang sudah ada), walau labelnya ikut masuk ke state collapsed tersimpan.
- Tombol disembunyikan pada breakpoint mobile (≤991px), konsisten dengan tombol collapse-sidebar-penuh.

## Acceptance criteria

- [x] Tombol toggle baru muncul di `.brand` sidebar Vue, terpisah dari tombol collapse-sidebar-penuh yang sudah ada.
- [x] Klik tombol saat ada grup collapsed → semua grup berlabel expand; klik lagi saat semua grup terbuka → semua grup collapse (kecuali grup yang memuat menu aktif, yang tetap terlihat terbuka).
- [ ] State hasil aksi ini tersimpan di `localStorage` key `sidebar-collapsed-groups` (key yang sama dipakai stack Blade di ticket 01) dan tetap konsisten setelah reload maupun navigasi Inertia.
- [ ] Tombol tidak tampil di breakpoint mobile (≤991px).
- [ ] Perilaku diverifikasi manual dan didokumentasikan di PR (klik toggle all di kedua arah, reload, cek localStorage, navigasi antar menu) — konsisten dengan keputusan testing 009 untuk Vue tanpa test runner JS.

## Blocked by

None (can start immediately).
