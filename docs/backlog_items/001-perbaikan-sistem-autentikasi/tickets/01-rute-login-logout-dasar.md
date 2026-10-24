## Parent

docs/backlog_items/001-perbaikan-sistem-autentikasi/spec.md

## What to build

Alur login dan logout dasar: pengguna bisa membuka `/login`, memasukkan email + password, dan masuk ke sistem lewat session (`Auth::attempt()`); pengguna yang sudah login bisa logout lewat `POST /logout` dan session-nya diakhiri. Tahap ini belum perlu tampilan final (komponen UI atomic design) — cukup form minimal yang berfungsi, karena desainnya jadi ticket terpisah.

## Acceptance criteria

- [x] `GET /login` menampilkan form login; kalau pengguna sudah login, redirect ke `/home`.
- [x] `POST /login` dengan kredensial benar membuat session dan redirect (ke `intended` URL kalau ada, atau `/home`).
- [x] `POST /login` dengan kredensial salah menampilkan pesan error dalam Bahasa Indonesia, tidak membuat session.
- [x] `POST /logout` mengakhiri session (invalidate + regenerate token) dan redirect ke `/login`.
- [x] Feature test baru (`tests/Feature/Auth/LoginTest.php`) mencakup keempat skenario di atas dan lulus (`php artisan test --filter=LoginTest`).

## Blocked by

None (can start immediately).
