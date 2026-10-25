## Parent

docs/backlog_items/019-perbaikan-eskalasi-hak-role/spec.md

## What to build

`RoleController@store` dan `@update` menolak matriks `hak` yang meminta menu/aksi di luar hak efektif akun yang melakukan request (aktor), kecuali aktor Super Admin. Error tampil di field `hak` lewat `ValidationException`, sama seperti penjagaan "tidak bisa mengunci diri sendiri" yang sudah ada.

## Implementation steps

- [ ] Tambah helper privat (mis. `pastikanHakDalamBatasAktor(Request $request, array $hakBaru): void`) di `RoleController`: ambil hak efektif aktor lewat `Akses::hakRole()` (role aktor sendiri), lalu untuk setiap `hakBaru[menu][aksi] === true`, tolak kalau hak aktor untuk `menu`/`aksi` itu `false`. Lewati pengecekan sepenuhnya bila `Akses::penuh($request->user())` (Super Admin).
- [ ] Panggil helper itu di `store()` sebelum `simpanHak()` dipanggil (termasuk saat insert role baru), dan di `update()` sebelum `simpanHak()` — setelah `normalisasiHak()`, sebelum transaksi DB.
- [ ] Lempar `ValidationException::withMessages(['hak' => '...'])` dengan pesan Bahasa Indonesia yang jelas (mis. "Anda tidak bisa memberi hak yang tidak Anda miliki sendiri.") saat ceiling dilanggar.
- [ ] Tes di `tests/Feature/Admin/RoleTest.php`: akun non-Super-Admin dengan hak terbatas mencoba `POST /role` dan `PUT /role/{id}` dengan `hak` melebihi haknya sendiri → `assertSessionHasErrors('hak')`, role/role_hak tidak berubah di database. Tes regresi: Super Admin tetap bisa menyimpan matriks apa pun; akun dengan hak terbatas tetap bisa menyimpan matriks yang masih dalam batas haknya (perilaku lama tidak rusak). Jalankan `php artisan test` dan `vendor/bin/pint`.

## Acceptance criteria

- [ ] Akun non-Super-Admin tidak bisa menyimpan (lewat tambah atau edit role) hak menu/aksi yang tidak ia miliki sendiri; request ditolak dengan error di field `hak`.
- [ ] Super Admin tidak terpengaruh — tetap bisa menyimpan matriks hak apa pun seperti sebelumnya.
- [ ] Akun non-Super-Admin dengan hak terbatas tetap bisa menyimpan role/matriks yang masih dalam batas hak miliknya sendiri (tidak ada regresi pada penggunaan normal).
- [ ] Penjagaan "tidak bisa mengunci diri sendiri dari menu Role" yang sudah ada tetap bekerja seperti sebelumnya.

## Blocked by

(tidak ada)
