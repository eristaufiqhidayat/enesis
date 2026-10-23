## Parent

docs/backlog_items/006-util-format-tanggal-tampilan/spec.md

## What to build

Buat `resources/js/utils/date.js` yang mengekspor dua fungsi:

1. `formatTanggal(value)` — untuk kolom bertipe `date` murni (string `Y-m-d`). Kembalikan string `dd Mmm yyyy` (mis. `01 Okt 2026`).
2. `formatTanggalWaktu(value)` — untuk kolom bertipe `datetime`/timestamp (string `Y-m-d H:i:s`, dipakai mis. untuk `created_at`/`updated_at`). Kembalikan string `dd Mmm yyyy, HH:mm` (mis. `01 Okt 2026, 08:30`, format jam 24-jam, tanpa detik).

Ketentuan untuk keduanya:

- Gunakan nama bulan singkat Indonesia yang deterministik (`Jan, Feb, Mar, Apr, Mei, Jun, Jul, Agu, Sep, Okt, Nov, Des`) lewat array manual — jangan hanya mengandalkan `Intl.DateTimeFormat('id-ID', ...)` tanpa verifikasi, karena singkatan bulan dari locale bisa berbeda antar environment.
- `value` berupa `null`, string kosong, atau tidak bisa diparse sebagai tanggal valid → kembalikan `-`.

## Acceptance criteria

- [ ] `resources/js/utils/date.js` ada dan mengekspor `formatTanggal` dan `formatTanggalWaktu`.
- [ ] `formatTanggal('2026-10-01')` → `'01 Okt 2026'`.
- [ ] `formatTanggalWaktu('2026-10-01 08:30:00')` → `'01 Okt 2026, 08:30'`.
- [ ] `formatTanggal(null)`, `formatTanggal('')`, `formatTanggalWaktu(null)`, `formatTanggalWaktu('')` → `'-'`.
- [ ] `formatTanggal('bukan-tanggal')` dan `formatTanggalWaktu('bukan-tanggal')` → `'-'` (tidak melempar exception).
- [ ] Ada test (Vitest/Jest kalau sudah tersedia di proyek; kalau belum ada test runner JS, dokumentasikan di PR kenapa test ditulis manual/di-skip) yang menutupi kasus-kasus di atas untuk kedua fungsi.

## Blocked by

None (can start immediately).
