## Parent

docs/backlog_items/001-perbaikan-sistem-autentikasi/spec.md

## What to build

Topbar aplikasi (dipakai di kedua stack rendering — halaman Blade lewat `ui.organisms.topbar` dan halaman Inertia/Vue lewat `Topbar.vue`) menampilkan nama pengguna yang sedang login dan tombol "Keluar" yang berfungsi.

## Acceptance criteria

- [x] Topbar versi Blade menampilkan nama user yang sedang login dan tombol/form logout (`POST /logout`).
- [x] Topbar versi Vue (halaman Inertia `Menu/Show.vue` dkk.) menampilkan nama user yang sedang login dan tombol logout, dengan data user dikirim lewat Inertia shared props (`HandleInertiaRequests::share()`).
- [x] Menekan tombol "Keluar" di kedua stack memicu `POST /logout` dan mengarahkan ke `/login`.
- [x] Tidak menduplikasi markup topbar yang sudah ada — perubahan dilakukan dengan menambah prop/slot pada komponen yang ada, bukan membuat komponen topbar baru (sesuai aturan atomic design di `CLAUDE.md`).

## Blocked by

- Ticket "Rute login & logout dasar"
