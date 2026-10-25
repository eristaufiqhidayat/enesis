## Parent

docs/backlog_items/021-menu-user-footer-pengaturan/spec.md

## What to build

Penyempurnaan tombol footer: state aktif saat halaman aktif ada di grup ini, serta perilaku saat sidebar dipersempit menjadi strip ikon.

## Acceptance criteria

- [x] Tombol footer disorot (gaya aktif seperti item top-level lain) selama `currentSlug` ada di dalam grup ini, terlepas dari popup terbuka/tutup.
- [x] Sidebar collapsed (`body.sidebar-collapsed`): tombol hanya menampilkan ikon avatar; nama/role tidak tampil di tombol tetapi tampil sebagai header di dalam popup; klik tetap membuka popup penuh (tidak terpotong `.sidebar` overflow). (Dicek di browser desktop: teks tombol `display: none`, popup 224px penuh di layar, header nama/role tampil.)
- [x] Mobile: popup tetap terjangkau dan tidak keluar layar. (Dicek di browser dengan halaman dalam iframe selebar 390px: popup 350×278 px, seluruhnya di dalam viewport, tidak ada overflow horizontal.)

## Implementation steps

- [x] `SidebarUserMenu.vue`: hitung state aktif dari `menu` + `currentSlug` (rekursif seperti `hasActiveDescendant` di `SidebarSubmenu.vue`). (Sudah ada dari ticket 01: `adaAktif` dan `grupAktif`.)
- [x] CSS collapsed + posisi popup (pertimbangkan `Teleport` ke body karena `.sidebar` memotong overflow); tooltip nama bila sesuai pola `SidebarNavList`. (CSS collapsed dan posisi `fixed` sudah ada, tanpa `Teleport` untuk popup karena `position: fixed` tidak terpotong `overflow`. Tooltip nama dibuat dengan `Teleport` ke body dan kelas `tooltip-floating`, seperti `SidebarNavList`; tidak tampil saat popup terbuka.)
- [x] Perluas `SidebarUserMenu.spec.js` (state aktif, mode collapsed); cek manual desktop, collapsed, dan mobile. (Spec state aktif, header popup, dan tooltip sudah ditambah dan lulus; mode collapsed juga dicek di browser: desktop, collapsed + tooltip, dan mobile.)

## Blocked by

- 01-footer-menu-user-popup.md
