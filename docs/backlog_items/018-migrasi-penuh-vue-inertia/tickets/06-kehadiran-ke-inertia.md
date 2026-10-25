## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Menu kehadiran — Absensi Sales, Absensi Sales Terlambat, Laporan Absensi Sales, Checkin Depo, Laporan Checkin Checkout — dirender lewat Inertia + Vue dengan filter, tabel, pagination, dan foto check-in yang sama seperti sekarang. Mengubah filter tidak lagi full reload, tapi query string tetap tercermin di URL.

## Acceptance criteria

- [x] Kelima menu kehadiran tampil dengan filter, tabel, dan pagination yang setara dengan versi Blade.
- [x] Mengubah filter memperbarui tabel tanpa full reload; URL tetap memuat query filter (bisa di-bookmark dan di-refresh).
- [x] Foto check-in tetap tampil dari rute foto yang sama.
- [x] Middleware `hak:<slug>` tidak berubah.
- [x] View Blade `kehadiran/*` dihapus.

## Implementation steps

- [x] Ubah `KehadiranController` (`absensi`, `checkinDepo`) ke `Inertia::render('Kehadiran/...')`; data paginasi dikirim dalam bentuk yang dipakai organism `DataTable`.
- [x] Buat komponen Vue filter kehadiran bersama (padanan partial filter Blade), memakai `router.get` dengan `preserveState`. (Ditaruh di `Pages/Kehadiran/partials/FilterKehadiran.vue`, bukan `Components/ui`, karena khusus kehadiran.)
- [x] Buat page Vue `Kehadiran/Absensi` dan `Kehadiran/CheckinDepo` dari `DataTable` dan komponen `ui`.
- [x] Hapus view Blade `kehadiran/*`. Catatan: `kunjungan` (Checkin Outlet) ikut dimigrasi ke `Kehadiran/Kunjungan.vue` karena `_filter.blade.php` dipakainya; `FilterKehadiran.vue` kini mendukung kolom outlet.
- [x] Ubah `tests/Feature/Admin/KehadiranTest.php`: assertion view/markup diganti pengecekan props Inertia (lewat helper `inertiaProps`, yang memakai `assertInertia` untuk komponen), assertion perilaku dipertahankan. `php artisan test` (199 tes) dan `vendor/bin/pint` hijau.

## Blocked by

- 02-coming-soon-ke-inertia.md
