## Parent

docs/backlog_items/012-pencarian-menu-sidebar/spec.md

## What to build

Tambahkan input pencarian menu di `.brand` sidebar (stack Blade), di bawah baris tombol collapse yang sudah ada (`resources/views/components/ui/organisms/sidebar.blade.php:3-7`), dengan dropdown hasil yang mengambang di bawah input.

- Markup: `<div class="sidebar-search">` berisi `<input type="search" data-sidebar-search placeholder="Cari menu...">` dan `<ul data-sidebar-search-results class="sidebar-search-results hidden">`, ditaruh di dalam `.brand` setelah `.brand-actions`.
- Data: flatten seluruh menu dari `Akses::menu(auth()->user())` (judul + slug, termasuk item di dalam `children` yang punya slug sendiri) jadi satu array datar, di-embed lewat `@json(...)` ke JS sekali di awal script yang sudah ada di file ini (pola sama dengan `currentSlug` di baris 1).
- Behavior JS: tiap `input` event pada `[data-sidebar-search]`, filter array datar dengan substring match case-insensitive pada `title`, render maksimal 8 hasil ke `[data-sidebar-search-results]` sebagai `<li><a href="/{slug}">{title}</a></li>`; kalau tidak ada match, render satu `<li>` non-klik berisi teks "Tidak ditemukan". Dropdown disembunyikan (`hidden`) saat query kosong.
- `Escape` pada input atau klik di luar `.sidebar-search` menyembunyikan dropdown tanpa mengosongkan input.
- CSS (`resources/css/app.css`): `border-bottom` pada `.brand` (reuse warna border `.section-label` yang sudah ada) memisahkan area tombol+search dari daftar menu di bawahnya; `.sidebar-search-results` diberi `position: absolute`, lebar penuh, background gelap, border-radius, box-shadow. Input & dropdown ikut disembunyikan di mode sidebar collapsed icon-only (`body.sidebar-collapsed .sidebar:not(:hover)`), sama seperti elemen lain yang sudah disembunyikan di mode itu.

## Acceptance criteria

- [x] Input pencarian dan container dropdown hasil muncul di `.brand` sidebar Blade, di bawah tombol collapse yang sudah ada.
- [x] Mengetik judul menu yang ada menampilkan hasil yang cocok (maks. 8) di dropdown; klik salah satu hasil menavigasi ke menu tersebut (full page load, `<a href>` biasa).
- [x] Mengetik kata yang tidak cocok dengan judul menu apa pun menampilkan baris "Tidak ditemukan", bukan dropdown kosong.
- [x] `Escape` pada input atau klik di luar area pencarian menyembunyikan dropdown.
- [x] `.brand` punya `border-bottom` yang memisahkan area tombol+search dari daftar grup/menu di bawahnya.
- [x] Mode sidebar collapsed icon-only (tombol `[data-sidebar-toggle]`) menyembunyikan input pencarian, konsisten dengan elemen lain yang sudah disembunyikan di mode itu.
- [x] Feature test Laravel memverifikasi markup `[data-sidebar-search]` dan `[data-sidebar-search-results]` muncul di `.brand`.

## Blocked by

None (can start immediately).
