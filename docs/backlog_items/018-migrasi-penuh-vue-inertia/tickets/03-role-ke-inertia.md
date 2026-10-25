## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Menu Role & hak akses (daftar role, tambah, edit matriks hak, lihat & pindah anggota, hapus) dirender lewat Inertia + Vue dengan perilaku sama seperti sekarang. Ini halaman form pertama yang dimigrasi, jadi tiket ini menetapkan pola form Inertia yang dipakai tiket berikutnya: `useForm` untuk submit & error validasi per field, flash message lewat shared prop, dan konfirmasi hapus lewat modal Vue.

## Acceptance criteria

- [x] Semua aksi `role.*` berjalan seperti sebelumnya lewat halaman Vue: list, tambah, edit matriks hak, lihat anggota, pindah anggota, hapus.
- [x] Error validasi tampil per field; pesan sukses/gagal (flash) tampil setelah redirect.
- [x] Konfirmasi hapus memakai modal Vue, bukan `confirm()` browser.
- [x] Tombol tambah/ubah/hapus mengikuti hak user (ditentukan server, diteruskan sebagai props); middleware `hak:role,*` tidak berubah.
- [x] Navigasi dari/ke halaman Role tanpa full reload.
- [x] View Blade `role/*` dihapus.

## Implementation steps

- [x] Tambah shared prop flash (pesan sesi yang saat ini dikirim controller lewat `->with(...)`) di `HandleInertiaRequests`.
- [x] Tambah komponen Vue yang dibutuhkan, padanan Blade yang sudah ada: atom `Label`, `Select`, `Checkbox`; molecule `Flash`, `Modal`. Tampilkan `Flash` di `AppLayout`.
- [x] Ubah `RoleController` (index, create/edit, anggota) dari `view(...)` ke `Inertia::render('Role/...')`, termasuk props hak user untuk tombol aksi; logika query & validasi tidak diubah.
- [x] Buat page Vue `Role/Index`, `Role/Form`, `Role/Anggota` dari komponen `ui`, memakai `useForm` untuk store/update/pindah dan `router.delete` + `Modal` untuk hapus.
- [x] Hapus view Blade `role/*`.
- [x] Ubah `tests/Feature/Admin/RoleTest.php`: assertion view/markup diganti `assertInertia`; assertion perilaku (redirect, isi database, 403) dipertahankan. Jalankan `php artisan test` dan `vendor/bin/pint`.

## Blocked by

- 02-coming-soon-ke-inertia.md
