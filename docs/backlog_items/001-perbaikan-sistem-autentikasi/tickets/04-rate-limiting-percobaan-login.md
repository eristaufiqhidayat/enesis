## Parent

docs/backlog_items/001-perbaikan-sistem-autentikasi/spec.md

## What to build

Batasi percobaan login yang gagal berulang-ulang dari kombinasi email+IP yang sama, supaya sistem terlindungi dari brute force, dengan pesan error yang jelas saat terkena limit.

## Acceptance criteria

- [x] Percobaan login gagal dihitung per kombinasi email + IP (pakai `RateLimiter` bawaan Laravel).
- [x] Setelah melewati batas percobaan (misal 5x), percobaan login berikutnya ditolak dengan pesan dalam Bahasa Indonesia yang menyebutkan harus menunggu beberapa saat.
- [x] Limit otomatis reset setelah jendela waktu terlampaui (misal 1 menit) atau setelah login berhasil.
- [x] Feature test baru membuktikan: percobaan ke-N gagal berturut-turut memicu lockout, dan request berikutnya dari IP/email lain tidak terpengaruh.

## Blocked by

- Ticket "Rute login & logout dasar"
