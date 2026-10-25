## Parent

docs/backlog_items/020-defer-data-tabel-menu/spec.md

## What to build

Terapkan pola defer + skeleton (dari tiket 01) ke `MotorisReportController` (tab Coverage/Performance/Absensi) dan `KuotaMotorisController` (isian kuota per depo + riwayat).

## Acceptance criteria

- [ ] `GET /motoris_report` menampilkan halaman (tab) tanpa menunggu query coverage/performance/absensi; area tabel menampilkan skeleton sampai data datang.
- [ ] Pindah tab (Coverage/Performance/Absensi) mengikuti perilaku partial reload yang sudah ada — tidak menampilkan skeleton ulang.
- [ ] `GET /kuota_motoris` menampilkan halaman (judul, form) tanpa menunggu query baris depo + riwayat isian; area tabel/riwayat menampilkan skeleton sampai data datang.
- [ ] Simpan kuota (`POST /kuota_motoris`) tidak terpengaruh — tetap request biasa, tidak di-defer; validasi, riwayat, dan pesan sukses/galat tetap sama seperti sebelumnya.
- [ ] Nilai Coverage/Performance/Absensi dan data kuota/riwayat yang ditampilkan identik dengan sebelum perubahan (regresi nilai).

## Implementation steps

- [ ] Identifikasi prop berat di `MotorisReportController@index` (tiga perhitungan tab) dan `KuotaMotorisController@index` (baris depo + riwayat), bungkus masing-masing dengan `Inertia::defer(...)` mengikuti pola tiket 01.
- [ ] Tambah skeleton (reuse `TableSkeleton.vue`) di `Pages/Motoris/Report.vue` dan `Pages/Motoris/Kuota.vue`, dibungkus `<Deferred>`.
- [ ] Update test terkait (`tests/Feature/MotorisReportTest.php`, test kuota motoris — cek nama aktual) untuk assert nilai tidak berubah setelah defer diterapkan.
- [ ] Verifikasi manual: buka Motoris Report (skeleton lalu data, pindah tab tanpa skeleton ulang), buka Kuota Motoris (skeleton lalu data), simpan & hapus kuota tetap berfungsi.
- [ ] Jalankan `php artisan test`, `npm run build`, `vendor/bin/pint`.

## Blocked by

docs/backlog_items/020-defer-data-tabel-menu/tickets/01-pola-defer-skeleton-menu-generik.md
