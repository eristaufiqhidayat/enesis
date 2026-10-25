## Parent

docs/backlog_items/021-menu-user-footer-pengaturan/spec.md

## What to build

Penyempurnaan tombol footer: state aktif saat halaman aktif ada di grup ini, serta perilaku saat sidebar dipersempit menjadi strip ikon.

## Acceptance criteria

- [ ] Tombol footer disorot (gaya aktif seperti item top-level lain) selama `currentSlug` ada di dalam grup ini, terlepas dari popup terbuka/tutup.
- [ ] Sidebar collapsed (`body.sidebar-collapsed`): tombol hanya menampilkan ikon avatar; nama/role tidak tampil di tombol tetapi tampil sebagai header di dalam popup; klik tetap membuka popup penuh (tidak terpotong `.sidebar` overflow).
- [ ] Mobile: popup tetap terjangkau dan tidak keluar layar.

## Implementation steps

- [ ] `SidebarUserMenu.vue`: hitung state aktif dari `menu` + `currentSlug` (rekursif seperti `hasActiveDescendant` di `SidebarSubmenu.vue`).
- [ ] CSS collapsed + posisi popup (pertimbangkan `Teleport` ke body karena `.sidebar` memotong overflow); tooltip nama bila sesuai pola `SidebarNavList`.
- [ ] Perluas `SidebarUserMenu.spec.js` (state aktif, mode collapsed); cek manual desktop, collapsed, dan mobile.

## Blocked by

- 01-footer-menu-user-popup.md
