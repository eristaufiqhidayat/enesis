## Parent

docs/backlog_items/001-perbaikan-sistem-autentikasi/spec.md

## What to build

Seluruh rute aplikasi yang sudah ada (catch-all menu `/{slug}`, `tracking_koordinator_history_location`, `tracking_sales_history_location`, `master_area/design_area`, halaman `coming_soon`) hanya bisa diakses oleh pengguna yang sudah login. Pengguna yang belum login diarahkan ke `/login`, dan setelah berhasil login diarahkan kembali ke halaman yang tadinya mereka tuju.

## Acceptance criteria

- [x] Semua rute di `routes/web.php` kecuali `/login` dibungkus middleware `auth`.
- [x] Request tanpa login ke rute apa pun (misal salah satu menu `/{slug}`, `/tracking_koordinator_history_location`, `/master_area/design_area`) redirect ke `/login`.
- [x] Setelah login berhasil, pengguna diarahkan kembali ke URL yang tadinya dituju (`redirect()->intended()`).
- [x] Semua feature test yang sudah ada dan menyentuh rute terlindungi (`MenuTest`, test tracking/design-area kalau ada) diupdate memakai `actingAs()` dan tetap lulus.
- [x] `php artisan test` lulus penuh.

## Blocked by

- Ticket "Rute login & logout dasar"
