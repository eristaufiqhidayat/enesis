## Parent

docs/backlog_items/017-ikon-menu-grup-tanpa-label/spec.md

## What to build

Render ikon per-item pada cabang item daun (tanpa children) di `resources/js/Components/ui/organisms/partials/SidebarNavList.vue`, mengimpor `Icon` dari `@/Components/ui/atoms/Icon.vue` dan menambahkan `<Icon v-if="item.icon" :name="item.icon" />` sebelum `<span class="nav-link-text">`, mengikuti pola `SidebarSubmenu.vue` yang sudah merender ikon untuk item ber-children.

## Acceptance criteria

- [x] `SidebarNavList.vue` mengimpor `Icon` dan cabang `v-else` (item daun, baris ~41-45) merender `<Icon v-if="item.icon" :name="item.icon" />` sebelum teks link.
- [ ] Halaman Inertia (`Menu/Show.vue`, dirender lewat `Sidebar.vue`) menampilkan ikon di depan "Beranda", "Monitoring Harian Sales", "Rekap Pencapaian SKU", "Summary All Depo", "Motoris Report".
- [ ] Item daun lain yang tidak punya `icon` di prop menu tetap tampil tanpa elemen ikon kosong/rusak.
- [ ] Sidebar Vue dalam keadaan disempitkan (strip ikon-saja, class `sidebar-collapsed` di `<body>`) tetap menampilkan ikon kelima menu ini dengan benar.

## Blocked by

None (can start immediately, independen dari tiket 01 — file dan stack rendering berbeda).
