## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Menu kehadiran — Absensi Sales, Absensi Sales Terlambat, Laporan Absensi Sales, Checkin Depo, Laporan Checkin Checkout — dirender lewat Inertia + Vue dengan filter, tabel, pagination, dan foto check-in yang sama seperti sekarang. Mengubah filter tidak lagi full reload, tapi query string tetap tercermin di URL.

## Acceptance criteria

- [ ] Kelima menu kehadiran tampil dengan filter, tabel, dan pagination yang setara dengan versi Blade.
- [ ] Mengubah filter memperbarui tabel tanpa full reload; URL tetap memuat query filter (bisa di-bookmark dan di-refresh).
- [ ] Foto check-in tetap tampil dari rute foto yang sama.
- [ ] Middleware `hak:<slug>` tidak berubah.
- [ ] View Blade `kehadiran/*` dihapus.

## Implementation steps

- [ ] Ubah `KehadiranController` (`absensi`, `checkinDepo`) ke `Inertia::render('Kehadiran/...')`; data paginasi dikirim dalam bentuk yang dipakai organism `DataTable`.
- [ ] Buat komponen Vue filter kehadiran bersama (padanan partial filter Blade), memakai `router.get` dengan `preserveState`.
- [ ] Buat page Vue `Kehadiran/Absensi` dan `Kehadiran/CheckinDepo` dari `DataTable` dan komponen `ui`.
- [ ] Hapus view Blade `kehadiran/*`.
- [ ] Ubah `tests/Feature/Admin/KehadiranTest.php`: assertion view/markup diganti `assertInertia`, assertion perilaku dipertahankan. Jalankan `php artisan test` dan `vendor/bin/pint`.

## Blocked by

- 02-coming-soon-ke-inertia.md
