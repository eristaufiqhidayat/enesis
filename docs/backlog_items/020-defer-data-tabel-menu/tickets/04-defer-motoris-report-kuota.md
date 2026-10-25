## Parent

docs/backlog_items/020-defer-data-tabel-menu/spec.md

## What to build

Terapkan pola defer + skeleton (dari tiket 01) ke `MotorisReportController` (tab Coverage/Performance/Absensi) dan `KuotaMotorisController` (isian kuota per depo + riwayat).

## Acceptance criteria

- [x] `GET /motoris_report` menampilkan halaman (tab) tanpa menunggu query coverage/performance/absensi; area tabel menampilkan skeleton sampai data datang. *(Diverifikasi di browser: kunjungan penuh dari sidebar memunculkan skeleton lalu data; test `loadDeferredProps` juga lulus.)*
- [x] Pindah tab (Coverage/Performance/Absensi) mengikuti perilaku partial reload yang sudah ada — tidak menampilkan skeleton ulang. *(Diverifikasi di browser dengan MutationObserver: pindah ke Performance dan Absensi tidak memunculkan skeleton.)*
- [x] `GET /kuota_motoris` menampilkan halaman (judul, form) tanpa menunggu query baris depo + riwayat isian; area tabel/riwayat menampilkan skeleton sampai data datang. *(Diverifikasi di browser: skeleton tampil saat memuat lalu tabel depo; Tampilkan tidak memunculkan skeleton. Riwayat tidak teramati karena data lokal kosong.)*
- [x] Simpan kuota (`POST /kuota_motoris`) tidak terpengaruh — tetap request biasa, tidak di-defer; validasi, riwayat, dan pesan sukses/galat tetap sama seperti sebelumnya. *(Diverifikasi: `test_admin_mengisi_kuota_berlaku_mulai_bulan` lulus, termasuk validasi dan pesan sukses.)*
- [x] Nilai Coverage/Performance/Absensi dan data kuota/riwayat yang ditampilkan identik dengan sebelum perubahan (regresi nilai). *(Diverifikasi: asersi nilai dijalankan lewat `loadDeferredProps`, 214 test lulus.)*

## Implementation steps

- [x] Identifikasi prop berat di `MotorisReportController@index` (tiga perhitungan tab) dan `KuotaMotorisController@index` (baris depo + riwayat), bungkus masing-masing dengan `Inertia::defer(...)` mengikuti pola tiket 01. *(Tab dibungkus sebagai satu prop `laporan`; depo dan riwayat masing-masing prop terpisah.)*
- [x] Tambah skeleton (reuse `TableSkeleton.vue`) di `Pages/Motoris/Report.vue` dan `Pages/Motoris/Kuota.vue`, dibungkus `<Deferred>`. *(Baris depo dipindah ke partial baru `partials/KuotaDepoTabel.vue` agar `useForm` dibuat setelah data tiba.)*
- [x] Update test terkait (`tests/Feature/Admin/MotorisReportTest.php`, `tests/Feature/MenuTest.php`) untuk assert nilai tidak berubah setelah defer diterapkan.
- [ ] Verifikasi manual: buka Motoris Report (skeleton lalu data, pindah tab tanpa skeleton ulang), buka Kuota Motoris (skeleton lalu data), simpan & hapus kuota tetap berfungsi. *(Sebagian sudah dilakukan di browser: skeleton lalu data dan pindah tab/Tampilkan tanpa skeleton. Belum: simpan & hapus kuota serta pagination Absensi, karena database lokal belum punya data depo dan hanya satu halaman data.)*
- [x] Jalankan `php artisan test`, `npm run build`, `vendor/bin/pint`. *(`php artisan test` 214/214, `npm run build` sukses, `vendor/bin/pint --test` lulus, `npm test` 34/34.)*

## Blocked by

docs/backlog_items/020-defer-data-tabel-menu/tickets/01-pola-defer-skeleton-menu-generik.md
