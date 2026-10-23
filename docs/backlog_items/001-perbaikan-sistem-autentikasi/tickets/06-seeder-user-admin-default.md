## Parent

docs/backlog_items/001-perbaikan-sistem-autentikasi/spec.md

## What to build

Seeder yang membuat satu akun admin default untuk environment lokal/dev, supaya setelah `composer setup` atau `php artisan migrate --seed` aplikasi punya kredensial yang bisa langsung dipakai untuk login — tanpa memasukkan data pribadi apa pun ke repo.

## Acceptance criteria

- [ ] `UserSeeder` baru membuat satu user admin dengan email dan password default yang didokumentasikan (misal di README atau komentar seeder), password disimpan lewat cast `hashed` yang sudah ada di model `User`.
- [ ] `UserSeeder` dipanggil dari `DatabaseSeeder` sehingga otomatis jalan lewat `composer setup` / `migrate --seed`.
- [ ] Tidak ada data pribadi/nyata yang masuk repo — hanya akun dev/demo.
- [ ] Setelah `migrate --seed`, login dengan kredensial default berhasil (diverifikasi manual atau lewat test).

## Blocked by

None (can start immediately).
