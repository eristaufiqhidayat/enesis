## Parent

docs/backlog_items/003-checkin-pertama-hari-ini-datetime/spec.md

## What to build

Ubah kolom `checkin_pertama_hari_ini` di tabel `checkin_pagi` dari `string(255)` menjadi `datetime` nullable lewat migrasi baru, dan sesuaikan semua kode yang membaca kolom ini (badge "sudah absen"/"belum absen" di tabel, dan KPI card "Sudah Absen"/"Belum Absen") agar tetap berfungsi persis seperti sekarang secara tampilan — hanya dasar perbandingannya berubah dari "string kosong vs tidak kosong" menjadi "`NULL` vs datetime terisi". Tiket ini TIDAK mengubah jalur impor CSV (`marketama:import`) — itu tiket terpisah yang di-blok oleh tiket ini.

## Acceptance criteria

- [ ] Migrasi baru mengubah tipe kolom `checkin_pertama_hari_ini` pada tabel `checkin_pagi` menjadi `datetime` nullable, berjalan bersih di SQLite (dev/test) dan didokumentasikan kalau ada keterbatasan driver lain.
- [ ] Badge di tabel `checkin_pagi` tetap tampil hijau ("terisi") kalau kolom berisi datetime valid, dan abu-abu "-" kalau `NULL` — tidak ada perubahan visual untuk kasus-kasus yang sudah ada.
- [ ] KPI "Sudah Absen"/"Belum Absen" di `MenuController` dihitung berdasarkan `whereNotNull`/`whereNull` saja (bukan lagi cek `!= ''`), dan hasilnya tetap benar.
- [ ] Test feature yang sudah ada (`CheckinPagiFilterTest`, `CheckinPagiKpiTest`) di-seed ulang dengan nilai datetime (bukan string jam seperti `'08:05'`) dan tetap lulus.
- [ ] `php artisan test` dan `vendor/bin/pint` lulus setelah perubahan.

## Blocked by

None (can start immediately).
