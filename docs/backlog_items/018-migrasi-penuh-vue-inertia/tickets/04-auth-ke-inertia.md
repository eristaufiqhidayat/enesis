## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Halaman login (termasuk tombol login demo) dan ganti password dirender lewat Inertia + Vue dengan layout auth tersendiri (tanpa sidebar/topbar), perilakunya sama seperti sekarang. Logout dari topbar Vue juga berjalan lewat Inertia, karena halaman login sudah bukan Blade lagi.

## Acceptance criteria

- [ ] Login, login demo, logout, dan ganti password berjalan sama; error kredensial/validasi tampil per field.
- [ ] Middleware `guest`, `auth`, dan `wajib-ganti-password` (paksa ganti password untuk akun dengan password awal) tetap bekerja, termasuk saat user bernavigasi lewat Inertia.
- [ ] Login dan logout tidak memicu error 419 (CSRF) di request Inertia berikutnya.
- [ ] Logout dari topbar Vue tidak lagi memakai form HTML biasa.
- [ ] View Blade `auth/*` dan `layouts/auth` dihapus.

## Implementation steps

- [ ] Buat layout Vue `AuthLayout` padanan `layouts/auth` Blade.
- [ ] Tambah molecule Vue `PasswordInput` (padanan `password-input` Blade, termasuk tombol tampil/sembunyikan) — dipakai ulang oleh tiket Akun.
- [ ] Ubah `LoginController@create` dan `GantiPasswordController@edit` ke `Inertia::render('Auth/...')`, props `wajib` untuk ganti password.
- [ ] Buat page Vue `Auth/Login` dan `Auth/GantiPassword` memakai `AuthLayout`, `useForm`, dan komponen `ui`; tombol demo login memakai `router.post`.
- [ ] Ganti form logout di `Topbar.vue` jadi `router.post`/`Link` method post Inertia.
- [ ] Hapus view Blade `auth/*` dan `layouts/auth`.
- [ ] Ubah tes di `tests/Feature/Auth/`: assertion view/markup diganti `assertInertia`; assertion perilaku (redirect, sesi, middleware) dipertahankan. Jalankan `php artisan test` dan `vendor/bin/pint`.

## Blocked by

- 03-role-ke-inertia.md
