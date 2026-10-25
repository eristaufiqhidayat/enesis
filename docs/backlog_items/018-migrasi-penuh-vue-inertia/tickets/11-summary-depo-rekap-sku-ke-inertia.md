## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Merge `origin/main` (PR #65-#71) ke branch `BI-018` membawa dua halaman baru yang ditulis sebagai Blade sebelum tiket 09 menghapus stack Blade: Summary All Depo (`summary_all_depo`, kartu per depo + detail per koordinator + rincian penjualan) dan Rekap Pencapaian SKU (`rekap_pencapaian_sku`, tab Per Motoris & Per Depo). Supaya merge tidak menghapus fitur dari main, layout Blade (`layouts.app`, `layouts.auth`) dan pustaka komponen Blade (`resources/views/components/ui/**`) dipulihkan sementara khusus untuk dua halaman ini. Tiket ini memindahkan keduanya ke Inertia + Vue agar stack Blade bisa dihapus lagi sesuai tiket 09.

## Acceptance criteria

- [ ] `SummaryDepoController` (index, detail, penjualan, simpanTarget) dirender lewat Inertia; halaman Summary All Depo punya perilaku sama: kartu per depo, filter tanggal/cari, chart per metrik, modal setting target, detail per koordinator, rincian penjualan per koordinator.
- [ ] `RekapPencapaianSkuController` (index, simpanTarget) dirender lewat Inertia; tab Per Motoris & Per Depo, filter tanggal, setting target tetap berfungsi sama.
- [ ] View Blade `motoris/summary-depo*.blade.php` dan `motoris/rekap-sku*.blade.php` dihapus.
- [ ] Layout Blade (`layouts/app.blade.php`, `layouts/auth.blade.php`) dan `resources/views/components/ui/**` dihapus lagi (tidak ada lagi consumer Blade).
- [ ] `composer test`, `npm run build`, `vendor/bin/pint` lulus; verifikasi manual di browser untuk kedua halaman.

## Implementation steps

- [ ] Port Summary All Depo ke `Pages/Motoris/SummaryDepo.vue` (+ partial detail/penjualan), pakai komponen Vue yang sudah ada (`Card`, `Modal`, `Select`, dst.) sesuai pola tiket 10.
- [ ] Port Rekap Pencapaian SKU ke `Pages/Motoris/RekapSku.vue` (tab Per Motoris/Per Depo).
- [ ] Hapus view Blade dan layout/komponen Blade yang jadi tidak terpakai lagi.
- [ ] Update tes `SummaryDepoTest` dan `RekapPencapaianSkuTest` dari `assertSee`/`assertViewIs` ke `assertInertia`.

## Blocked by

Tidak ada.
