## Parent

docs/backlog_items/012-pencarian-menu-sidebar/spec.md

## What to build

Tambahkan input pencarian menu di `.brand` sidebar (stack Vue/Inertia, `resources/js/Components/ui/organisms/Sidebar.vue`), di bawah `.brand-actions` yang sudah ada (baris 129-141), dengan dropdown hasil mengambang, perilaku dan styling CSS konsisten dengan ticket 01 (Blade).

- Markup: `<div class="sidebar-search">` dengan `<input type="search" data-sidebar-search placeholder="Cari menu...">` (`v-model` ke `ref` query lokal) dan `<ul class="sidebar-search-results" :class="{ hidden: !showResults }">` berisi hasil.
- Data: `computed` yang meratakan `menu.value` (item biasa + `item.children` yang punya `slug`) jadi satu array `{ title, slug }`, dihitung ulang otomatis saat `menu` berubah (tidak perlu `onMounted` manual seperti di Blade karena ini reaktif Vue).
- Behavior: `computed` hasil filter dari array datar berdasarkan `query` (substring, case-insensitive pada `title`), dibatasi 8 item teratas. Hasil kosong saat `query` kosong atau dropdown tidak difokus (`showResults` di-toggle lewat `@focus`/`@blur` dengan delay kecil supaya klik hasil tetap terdaftar sebelum `blur` menyembunyikan dropdown — pola umum untuk combobox sederhana). Tidak ada match → render satu baris "Tidak ditemukan".
- Navigasi hasil pakai `<Link :href="`/${item.slug}`">` dari Inertia (komponen `Link` yang sudah diimpor di file ini), konsisten dengan `nav-link` lain.
- `Escape` pada input (`@keydown.esc`) menyembunyikan dropdown (`showResults = false`) tanpa mengosongkan `query`.
- CSS: reuse class yang sama dengan Blade (`.sidebar-search`, `.sidebar-search-results`) yang ditambahkan ke `resources/css/app.css` di ticket 01 — tidak menduplikasi style baru khusus Vue karena kedua stack memakai stylesheet yang sama.

## Acceptance criteria

- [x] Input pencarian dan dropdown hasil muncul di `.brand` Sidebar.vue, di bawah `.brand-actions` yang sudah ada.
- [x] Mengetik judul menu yang ada menampilkan hasil yang cocok (maks. 8, reaktif lewat `computed`) di dropdown; klik salah satu hasil menavigasi lewat Inertia `Link` ke menu tersebut.
- [x] Mengetik kata yang tidak cocok dengan judul menu apa pun menampilkan baris "Tidak ditemukan".
- [x] `Escape` pada input, atau blur (klik di luar area pencarian), menyembunyikan dropdown.
- [x] Mode sidebar collapsed icon-only (`collapsedFull`/class `sidebar-collapsed` di `<body>`) menyembunyikan input pencarian, konsisten dengan elemen lain yang sudah disembunyikan di mode itu lewat CSS yang sama dengan Blade.
- [ ] Perilaku diverifikasi manual dan didokumentasikan di PR (belum ada test runner JS untuk komponen Vue di proyek ini, konsisten dengan keputusan testing 009/010).

## Blocked by

None (can start immediately, independen dari ticket 01 — dua stack rendering berbeda).
