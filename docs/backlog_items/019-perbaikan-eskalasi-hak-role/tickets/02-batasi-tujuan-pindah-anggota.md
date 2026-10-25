## Parent

docs/backlog_items/019-perbaikan-eskalasi-hak-role/spec.md

## What to build

`RoleController@pindahkan` menolak pemindahan akun ke role tujuan (`ke`) yang hak-nya lebih luas daripada hak efektif aktor, atau ke role `super_admin`, kecuali aktor sendiri Super Admin. Error tampil di field `ke` lewat `ValidationException`.

## Implementation steps

- [ ] Tambah pengecekan di `pindahkan()` setelah validasi dasar (`$data['ke']` sudah lolos `Rule::exists`/`Rule::notIn`) dan sebelum `DB::table('users')->whereIn(...)->update(...)`: ambil role tujuan (`DB::table('roles')->find($data['ke'])`) dan hak-nya (`Akses::hakRole()`), bandingkan dengan hak efektif aktor. Tolak bila aktor bukan Super Admin (`! Akses::penuh($request->user())`) dan (role tujuan adalah `super_admin`, ATAU role tujuan punya `true` pada kombinasi menu/aksi yang aktor sendiri tidak punya).
- [ ] Lempar `ValidationException::withMessages(['ke' => '...'])` dengan pesan Bahasa Indonesia yang jelas (mis. "Anda tidak bisa memindahkan akun ke role dengan hak lebih luas dari hak Anda sendiri.") saat ceiling dilanggar. Pastikan ini terjadi sebelum query update ke tabel `users` dijalankan (tidak ada efek samping bila ditolak).
- [ ] Tes di `tests/Feature/Admin/RoleTest.php`: akun non-Super-Admin dengan hak `role.ubah` mencoba `POST /role/{id}/pindah` dengan `ke` = role `super_admin` → `assertSessionHasErrors('ke')`, `role_id` akun tujuan tidak berubah. Tambah juga kasus `ke` = role lain yang hak-nya lebih luas dari aktor (bukan super_admin) → ditolak juga. Tes regresi: akun non-Super-Admin tetap bisa memindahkan akun ke role yang hak-nya dalam batas haknya sendiri; Super Admin tetap bisa memindahkan ke role apa pun termasuk `super_admin`. Jalankan `php artisan test` dan `vendor/bin/pint`.

## Acceptance criteria

- [ ] Akun non-Super-Admin tidak bisa memindahkan akun apa pun ke role `super_admin` lewat `/role/{id}/pindah`.
- [ ] Akun non-Super-Admin tidak bisa memindahkan akun ke role lain yang hak-nya lebih luas dari hak miliknya sendiri.
- [ ] Super Admin tidak terpengaruh — tetap bisa memindahkan akun ke role apa pun termasuk `super_admin`.
- [ ] Pemindahan ke role yang hak-nya masih dalam batas hak aktor tetap berjalan normal (tidak ada regresi).
- [ ] Penjagaan "tidak bisa memindahkan akun sendiri" yang sudah ada tetap bekerja seperti sebelumnya.

## Blocked by

(tidak ada)
