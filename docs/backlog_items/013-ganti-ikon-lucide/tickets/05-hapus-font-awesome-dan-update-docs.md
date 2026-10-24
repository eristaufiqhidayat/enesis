## Parent

docs/backlog_items/013-ganti-ikon-lucide/spec.md

## What to build

Setelah tiket 02-04 selesai dan grep `fas fa-` di `resources/` & `config/` kosong, hapus Font Awesome sepenuhnya: folder `public/vendor/fontawesome/` dan `<link>` stylesheet-nya di `resources/views/app.blade.php`, `resources/views/layouts/app.blade.php`, `resources/views/layouts/auth.blade.php`. Update `docs/STYLE_GUIDE.md` dan `docs/TECH.md` supaya menyebut Lucide (self-hosted di `public/vendor/lucide/`) sebagai sistem ikon, mengganti referensi ke Font Awesome.

## Acceptance criteria

- [x] `grep -r "fas fa-" resources/ config/` tidak menghasilkan match apa pun.
- [x] `public/vendor/fontawesome/` dihapus dari repo.
- [x] Tidak ada lagi `<link>` ke `vendor/fontawesome/css/all.min.css` di `app.blade.php`, `layouts/app.blade.php`, `layouts/auth.blade.php`.
- [x] `docs/STYLE_GUIDE.md` dan `docs/TECH.md` menyebut Lucide (bukan Font Awesome) sebagai sistem ikon aplikasi.
- [ ] Full test suite (`composer test`) tetap hijau setelah penghapusan.
- [ ] Verifikasi manual: jalankan aplikasi, pastikan tidak ada ikon yang hilang/rusak di halaman mana pun (sidebar, topbar, login, area design, tracking) setelah stylesheet Font Awesome dicabut.

## Blocked by

- 02-migrasi-config-menu-dan-sidebar.md
- 03-migrasi-topbar-dan-password-input.md
- 04-migrasi-area-design-dan-tracking-history.md
