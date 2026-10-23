## Parent

docs/backlog_items/009-sidebar-grup-collapsible/spec.md

## What to build

Ubah `resources/js/Components/ui/organisms/Sidebar.vue` supaya tiap grup berlabel bisa di-collapse, dengan state yang kompatibel dengan versi Blade (ticket 01) lewat `localStorage` key yang sama (`sidebar-collapsed-groups`, array label grup yang collapsed):

1. Tambah `ref` untuk state collapse per grup, diinisialisasi dari `localStorage.getItem('sidebar-collapsed-groups')` saat `onMounted` (konsisten dengan pola `SCROLL_KEY` yang sudah ada di baris 14-23).
2. Tambah fungsi `isGroupOpen(section)` — grup tanpa `label` selalu `true`; grup berlabel: `true` kalau tidak ada di array collapsed ATAU grup memuat `currentSlug` (cek lewat `section.items`, termasuk `children`), mengikuti pola `isChildOpen` yang sudah ada (baris 25-29).
3. Tambah fungsi `toggleGroup(section)` yang menambah/menghapus `section.label` dari array collapsed di state + `localStorage`, dipanggil dari `@click.prevent` pada `section-label`.
4. Ubah template: `section-label` jadi `<a>`/`<button>` clickable dengan chevron saat `section.label` ada, dan `<ul>` item grup diberi `:class="{ hidden: !isGroupOpen(section) }"`.

## Acceptance criteria

- [x] Setiap grup berlabel di sidebar Vue punya toggle collapse/expand, perilaku sama dengan versi Blade (ticket 01) untuk input yang sama.
- [x] Toggle grup menulis ke `localStorage` key `sidebar-collapsed-groups` dengan format array yang sama seperti yang dibaca/ditulis versi Blade (ticket 01) — collapse di satu stack lalu pindah ke stack lain (atau reload) menghasilkan state collapse yang sama.
- [x] Navigasi Inertia ke menu di grup yang collapsed menurut `localStorage` tetap menampilkan grup itu terbuka.
- [x] Tidak ada exception di console saat `localStorage` kosong/belum pernah diisi (first visit).

## Blocked by

- Ticket 01 (collapse grup Blade) — perlu format `localStorage` (key, bentuk array, aturan auto-open untuk grup aktif) yang sudah ditetapkan di sana supaya kedua stack kompatibel.
