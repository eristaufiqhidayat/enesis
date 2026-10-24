## Parent

docs/backlog_items/017-ikon-menu-grup-tanpa-label/spec.md

## What to build

Render ikon per-item pada cabang item daun (tanpa children) di `resources/views/components/ui/organisms/partials/sidebar-nav-items.blade.php`, memakai `<x-ui.atoms.icon :name="$item['icon']" />` dibungkus `@if (! empty($item['icon']))`, sama seperti pola yang sudah dipakai di header flyout item ber-children pada file yang sama.

## Acceptance criteria

- [x] Cabang `@else` (item daun, baris ~37-42) di `sidebar-nav-items.blade.php` merender `<x-ui.atoms.icon :name="$item['icon']" />` sebelum `<span class="nav-link-text">`, hanya jika `$item['icon']` tidak kosong.
- [x] Sidebar halaman non-Inertia (Blade biasa, mis. `menu.coming_soon`, `tracking/`, `area/`) menampilkan ikon di depan "Beranda", "Monitoring Harian Sales", "Rekap Pencapaian SKU", "Summary All Depo", "Motoris Report".
- [x] Item daun lain yang tidak punya `icon` di config tetap tampil tanpa elemen ikon kosong/rusak.
- [x] Sidebar dalam keadaan disempitkan (strip ikon-saja) tetap menampilkan ikon kelima menu ini dengan benar.

## Blocked by

None (can start immediately — perubahan lokal ke satu partial Blade, tidak bergantung pekerjaan lain).
