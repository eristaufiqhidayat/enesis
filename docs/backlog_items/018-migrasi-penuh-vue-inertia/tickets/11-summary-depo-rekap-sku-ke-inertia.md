## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Merge `origin/main` (PR #65-#71) ke branch `BI-018` membawa dua halaman baru yang ditulis sebagai Blade sebelum tiket 09 menghapus stack Blade: Summary All Depo (`summary_all_depo`, kartu per depo + detail per koordinator + rincian penjualan) dan Rekap Pencapaian SKU (`rekap_pencapaian_sku`, tab Per Motoris & Per Depo). Supaya merge tidak menghapus fitur dari main, layout Blade (`layouts.app`, `layouts.auth`) dan pustaka komponen Blade (`resources/views/components/ui/**`) dipulihkan sementara khusus untuk dua halaman ini. Tiket ini memindahkan keduanya ke Inertia + Vue agar stack Blade bisa dihapus lagi sesuai tiket 09.

## Acceptance criteria

- [x] `SummaryDepoController` (index, detail, penjualan, simpanTarget) dirender lewat Inertia; halaman Summary All Depo punya perilaku sama: kartu per depo, filter tanggal/cari, chart per metrik, modal setting target, detail per koordinator, rincian penjualan per koordinator. *Modal "Lihat Detail" dan "Total Sales" (fetch potongan HTML) diganti navigasi Inertia biasa ke halaman detail/penjualan tersendiri — sudah SPA, jadi tidak perlu lagi trik fetch-ke-modal dari era Blade full-reload.*
- [x] `RekapPencapaianSkuController` (index, simpanTarget) dirender lewat Inertia; tab Per Motoris & Per Depo, filter tanggal, setting target tetap berfungsi sama.
- [x] View Blade `motoris/summary-depo*.blade.php` dan `motoris/rekap-sku*.blade.php` dihapus.
- [x] Layout Blade (`layouts/app.blade.php`, `layouts/auth.blade.php`) dan `resources/views/components/ui/**` dihapus lagi (tidak ada lagi consumer Blade).
- [ ] `composer test`, `npm run build`, `vendor/bin/pint` lulus; verifikasi manual di browser untuk kedua halaman. *`php artisan test` lulus (211 tes), `npm run build` sukses, `vendor/bin/pint` bersih. Verifikasi browser (`127.0.0.1:8000`, Demo Super Admin): halaman index Rekap Pencapaian SKU (dua tab) dan Summary All Depo (filter tanggal, modal Chart, modal Setting Target) tampil tanpa error console, navigasi sidebar & tab lewat Inertia tanpa full reload. Belum: klik ke halaman detail/penjualan per depo (database lokal belum berisi data motoris untuk dicoba).*

## Implementation steps

- [x] Port Summary All Depo ke `Pages/Motoris/SummaryDepo.vue` (+ partial detail/penjualan), pakai komponen Vue yang sudah ada (`Card`, `Modal`, `Select`, dst.) sesuai pola tiket 10. *Detail & penjualan jadi halaman Inertia tersendiri (`SummaryDepoDetail.vue`, `SummaryDepoPenjualan.vue`) bukan partial di dalam modal, lihat catatan di acceptance criteria pertama.*
- [x] Port Rekap Pencapaian SKU ke `Pages/Motoris/RekapSku.vue` (tab Per Motoris/Per Depo).
- [x] Hapus view Blade dan layout/komponen Blade yang jadi tidak terpakai lagi.
- [x] Update tes `SummaryDepoTest` dan `RekapPencapaianSkuTest` dari `assertSee`/`assertViewIs` ke `assertInertia`.

## Blocked by

Tidak ada.
