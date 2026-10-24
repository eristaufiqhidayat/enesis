## Parent

docs/backlog_items/013-ganti-ikon-lucide/spec.md

## What to build

Pindahkan ikon statis di topbar dan password-input dari `<i class="fas fa-*">` mentah ke Icon atom (tiket 01), dengan nama Lucide yang sepadan. Unduh file SVG Lucide tambahan yang dibutuhkan ke `public/vendor/lucide/`.

- `resources/views/components/ui/organisms/topbar.blade.php` & `resources/js/Components/ui/organisms/Topbar.vue`: ganti `fa-truck-loading`, `fa-boxes`, `fa-draw-polygon`, `fa-bell`, `fa-bell-slash`, `fa-user-circle` jadi pemanggilan Icon atom dengan nama Lucide yang representatif (mapping final ditentukan saat implementasi, mengikuti makna semantik tiap tombol).
- `resources/views/components/ui/molecules/password-input.blade.php`: ganti `fa-eye`/`fa-eye-slash` jadi Icon atom; logic toggle JS yang sekarang mengganti `className` elemen `<i>` (`tombol.querySelector('i').className = ...`) diganti pendekatan yang sepadan untuk SVG (misal dua elemen Icon `eye`/`eye-off` yang di-toggle `hidden`-nya, bukan ganti nama ikon on-the-fly).

## Acceptance criteria

- [x] `topbar.blade.php` dan `Topbar.vue` tidak lagi mengandung `fas fa-*` sama sekali; semua ikon tombol aksi & notifikasi dirender lewat Icon atom.
- [x] `password-input.blade.php` tidak lagi mengandung `fas fa-eye`/`fas fa-eye-slash`; toggle show/hide password tetap berfungsi (klik tombol mata mengganti ikon dan `type` input).
- [x] Feature test yang mem-render topbar/password-input component (pola `Blade::render`) meng-assert tidak ada `fas fa-` di output dan elemen `<svg>` muncul di tempat yang diharapkan.
- [x] Verifikasi manual: tombol shortcut topbar, notifikasi bell, user menu, dan toggle show/hide password di form login/ganti-password semuanya tampil Lucide dan tetap berfungsi seperti sebelumnya.

## Blocked by

- 01-icon-atom-lucide-svg.md
