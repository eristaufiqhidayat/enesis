## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Dua halaman motoris yang masih dirender Blade (`layouts.app`) dipindah ke Inertia + Vue dengan perilaku yang sama: Motoris Report (tab Coverage, Performance, Absensi) dan Kuota Motoris (isian kuota per depo, riwayat isian). Ini membuat tiket 09 bisa menghapus layout Blade. Tidak ada tiket 018 lain yang mencakup dua halaman ini, jadi tiket ini ditambahkan sebagai tiket tersendiri.

## Acceptance criteria

- [x] Motoris Report dan Kuota Motoris dirender lewat Inertia (`Motoris/Report`, `Motoris/Kuota`); controller tidak lagi memakai `view()`.
- [x] Perhitungan Coverage, Performance, dan Absensi tidak berubah (dites lewat props Inertia dengan nilai yang sama seperti sebelumnya).
- [x] Simpan kuota tetap memvalidasi isian, mencatat riwayat admin, dan menampilkan pesan sukses/galat.
- [x] Cakupan depo dan hak `motoris_report` / `kuota_motoris` (lihat, ubah) tetap dicek di server; user tanpa hak mendapat 403.
- [x] View Blade `motoris/report` dan `motoris/kuota` dihapus.
- [ ] Verifikasi manual di browser: pindah tab, filter absensi, pagination, simpan dan hapus kuota, tombol "Atur kuota motoris", serta navigasi Inertia bolak-balik tanpa error console. *Sebagian (login Demo Super Admin di `localhost:8001`): pindah tab Coverage → Performance → Absensi, filter absensi (`q` masuk URL, hilang saat pindah ke Coverage), tombol "Atur kuota motoris" membuka `/kuota_motoris` lewat navigasi Inertia dengan breadcrumb benar, sidebar kembali ke Beranda, dan tidak ada error console. Belum: pagination dan baris data (database lokal belum berisi data depo/motoris), simpan dan hapus kuota (menulis data, belum diizinkan), serta akses 403 untuk user tanpa hak.*

## Implementation steps

- [x] Ubah `MotorisReportController@index` ke `Inertia::render('Motoris/Report')` dengan props tab, periode, filter, daftar depo/koordinator, baris, dan total.
- [x] Ubah `KuotaMotorisController@index` ke `Inertia::render('Motoris/Kuota')`; baris depo dan riwayat dipetakan di controller (tanggal sudah diformat server-side seperti sebelumnya).
- [x] Buat `Pages/Motoris/Report.vue` (memakai `Tabs`, `Card`, `Pagination`) dengan partial `Coverage.vue`, `Performance.vue`, dan `Absensi.vue`.
- [x] Buat `Pages/Motoris/Kuota.vue` memakai `useForm`; galat validasi per depo ditampilkan lewat `FieldError`.
- [x] Tambah `desimal()` di `resources/js/Support/format.js` (padanan `number_format(..., 1)`).
- [x] Ubah tes `MotorisReportTest` dan `MenuTest` dari `assertSee`/`assertViewIs` ke `assertInertia`; hapus view Blade motoris.
- [x] Jalankan `php artisan test` (192 tes hijau), `npm run build` (sukses), dan `vendor/bin/pint` (bersih).
- [ ] Verifikasi manual di browser (lihat acceptance criteria). *Sebagian: alur navigasi dan tab terverifikasi, lihat acceptance criteria di atas.*

## Blocked by

Tidak ada.
