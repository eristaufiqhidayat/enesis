## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Empat menu Users (kelola akun login) dirender lewat Inertia + Vue dengan perilaku sama seperti sekarang: daftar, detail, tambah/edit (termasuk pencarian karyawan), atur password, ubah status, cabut sesi, pakai akun, hapus, dan khusus koordinator: atur sales bawahan serta outlet sales.

Halaman yang menampilkan password baru perlu perhatian khusus. Saat ini password ditampilkan langsung dari respons POST (tidak lewat sesi) dengan `Cache-Control: no-store`. Inertia menyimpan props halaman di riwayat browser (`history.state`), jadi tanpa penanganan khusus password itu akan tersimpan di riwayat browser.

## Acceptance criteria

- [ ] Semua rute `akun.{slug}.*` berjalan sama lewat halaman Vue untuk keempat menu Users, termasuk rute khusus `user_koordinator` (cari/tambah/lepas sales).
- [ ] Pencarian karyawan dan sales di form tetap memanggil endpoint JSON yang sama.
- [ ] Halaman password baru tidak menyimpan password di riwayat browser dalam bentuk terbaca (memakai history encryption Inertia), respons tetap `no-store`, dan password tidak muncul lagi saat user menekan Back.
- [ ] "Pakai akun" berpindah sesi dengan benar (full redirect bila perlu, mis. `Inertia::location`).
- [ ] Tombol aksi mengikuti hak user; middleware `hak:{slug},*` tidak berubah.
- [ ] View Blade `akun/*` dihapus.

## Implementation steps

- [ ] Tambah komponen Vue yang masih kurang dan dipakai di form akun (mis. atom `Textarea`, `Radio`), padanan Blade yang ada.
- [ ] Ubah `AkunController` (index, show, create/edit, tampil password) ke `Inertia::render('Akun/...')`, termasuk props hak user; logika query & validasi tidak diubah.
- [ ] Aktifkan history encryption untuk respons halaman password dan pertahankan header `no-store`.
- [ ] Buat page Vue `Akun/Index`, `Akun/Show`, `Akun/Form`, `Akun/Password`, plus komponen untuk bagian sales bawahan dan outlet sales, memakai `useForm`, `PasswordInput`, `Modal`, `Flash`.
- [ ] Pastikan "pakai akun" melakukan full redirect bila sesi berganti.
- [ ] Hapus view Blade `akun/*`.
- [ ] Ubah `AkunTest`, `KoordinatorSalesTest`, `OutletSalesTest`: assertion view/markup diganti `assertInertia`, assertion perilaku dipertahankan; tambah tes bahwa respons password ber-header `no-store`. Jalankan `php artisan test` dan `vendor/bin/pint`.

## Blocked by

- 04-auth-ke-inertia.md
