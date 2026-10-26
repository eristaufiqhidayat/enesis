## Parent

docs/backlog_items/024-summary-all-depo-tampilan-tabel/spec.md

## What to build

Tracer-bullet end-to-end: tambahkan tab **Kartu | Tabel** di `Motoris/SummaryDepo.vue`. Tab Tabel menampilkan satu baris per depo (kolom pertama sticky) dengan semua metrik kartu sebagai kolom, memakai prop `kartu` yang sama. Pilihan tab disimpan di query `?tampilan=`.

## Acceptance criteria

- [x] Halaman `/summary_all_depo` menampilkan tab Kartu | Tabel (komponen `Tabs`); bawaan tetap Kartu.
- [x] Tab Tabel menampilkan satu baris per depo dengan kolom: depo+distributor, SK, Motoris, Sales, Call, EC, AVG Hari Kerja, AVG Call, Call Achievement, AVG EC, EC Achievement, AVG Sales, AVG IPT, IPT Achievement, AVG Point, SK Visit, Visit SK, AVG Visit SK.
- [x] Kolom depo sticky saat tabel digeser horizontal; nama depo menaut ke detail, Sales menaut ke Detail EC & Penjualan.
- [x] Kolom Achievement berwarna sesuai `warnaCapai`; angka diformat sama dengan kartu.
- [x] Filter tanggal dan cari depo berlaku untuk kedua tab; `?tampilan=tabel` bertahan setelah filter diganti; nilai tidak valid jatuh ke kartu.
- [x] Kolom bisa diurutkan (memakai fitur sort DataTable 023 bila sudah tersedia).
- [x] Baris total di bawah tabel; nilainya cocok dengan jumlah/rata-rata dari kartu.
- [x] Tampilan kartu tidak berubah (markup kartu sama, hanya `warnaCapai`/`rupiah` dipindah ke Support).

## Implementation steps

- [x] `resources/css/app.css`: class komponen untuk kolom sticky dan sel capaian (bukan utility panjang di template).
- [x] `DataTable.vue` tidak diubah: footer-nya (Total N data + Pagination) dan sort-nya berbasis server tidak cocok untuk tabel 30 baris tanpa paginasi. Tabel dibuat sebagai partial `SummaryDepoTabel.vue` di atas class `.data-table` dengan sort sisi klien; logika di `resources/js/Support/summaryDepo.js`.
- [x] Ekstrak komponen tabel ke `resources/js/Pages/Motoris/partials/SummaryDepoTabel.vue`. Spec Vitest ada untuk logika (`Support/summaryDepo.spec.js`: total berbobot, warna capai, format); komponen tabelnya sendiri belum punya spec render.
- [x] `resources/js/Pages/Motoris/SummaryDepo.vue`: pasang `Tabs`, sinkronkan dengan query `tampilan`, render kartu atau tabel dari prop `kartu` yang sama.
- [x] `tests/Feature/Admin/SummaryDepoTest.php`: tambah assertion untuk halaman dengan `?tampilan=tabel`.
- [ ] Jalankan `npm test`, `npm run build`, `php artisan test --filter=SummaryDepo`, `vendor/bin/pint` — semuanya lulus; cek manual di browser belum dilakukan (navigasi Browser pane ditolak).

## Blocked by

Tidak ada. (Sort per kolom memakai fitur 023 bila sudah selesai; kalau belum, kolom tetap tampil tanpa sort.)
