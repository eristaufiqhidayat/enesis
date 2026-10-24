## Parent

docs/backlog_items/013-ganti-ikon-lucide/spec.md

## What to build

Migrasikan field `'icon'` di seluruh entri `menu` (119+ menu + grup) di `config/marketama.php` dari full class string (`'fas fa-home'`) jadi nama ikon Lucide polos (`'home'`), lewat mapping manual 1:1 berdasarkan makna semantik tiap menu. Unduh semua file SVG Lucide hasil mapping ke `public/vendor/lucide/` yang belum ada dari tiket 01. Ganti kedua partial sidebar supaya memanggil Icon atom (dari tiket 01) bukan `<i class="...">` mentah lagi.

- `resources/views/components/ui/organisms/partials/sidebar-nav-items.blade.php`: `<i class="{{ $item['icon'] }}"></i>` → `<x-ui.atoms.icon :name="$item['icon']" class="nav-link-icon-svg" />` (sesuaikan class CSS existing untuk ukuran/warna ikon), termasuk untuk `$child['icon']` dan `.nav-chevron` (`fa-chevron-down` → Icon atom `chevron-down`).
- `resources/js/Components/ui/organisms/Sidebar.vue`: `<i :class="item.icon">` → `<Icon :name="item.icon" />`, termasuk `child.icon` dan chevron.

## Acceptance criteria

- [x] Semua nilai `'icon'` di `config('marketama.menu')` (termasuk level grup dan `children`) tidak lagi berawalan `'fas '`/`'far '`.
- [x] Setiap nama ikon di `config('marketama.menu')` punya file SVG yang sesuai di `public/vendor/lucide/`.
- [x] Render `<x-ui.organisms.sidebar />` tidak mengandung string `fas fa-` sama sekali.
- [x] Setiap entri menu/grup berlabel di `config('marketama.menu')` menghasilkan elemen `<svg>` di output sidebar (lewat Icon atom), jumlahnya cocok dengan jumlah entri yang punya `icon`.
- [x] Update test `tests/Feature/SidebarCollapseGrupTest.php` (atau tes baru) yang sebelumnya mengasumsikan `fas fa-*`/`nav-chevron` sebagai class `<i>`, supaya sesuai assertion `<svg>` yang baru.
- [x] Verifikasi manual: sidebar (Blade & Vue/Inertia) menampilkan ikon Lucide yang representatif untuk tiap menu, tidak ada ikon kosong/patah.

## Blocked by

- 01-icon-atom-lucide-svg.md
