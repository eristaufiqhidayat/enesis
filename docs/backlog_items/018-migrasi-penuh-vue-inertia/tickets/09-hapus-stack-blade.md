## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Setelah semua halaman web dirender lewat Inertia, aplikasi hanya punya satu stack rendering. Layout Blade aplikasi, sidebar/topbar Blade beserta vanilla JS-nya, dan komponen Blade yang tidak lagi dipakai dihapus, sehingga perubahan sidebar/topbar berikutnya cukup dikerjakan sekali di Vue. Penanganan full reload ke halaman Blade (tiket #45) juga dibuang karena tidak ada lagi halaman Blade yang dituju.

## Acceptance criteria

- [x] Tidak ada lagi controller/rute halaman web yang merender view Blade; satu-satunya view halaman adalah root template Inertia. *Diverifikasi: `resources/views/` hanya berisi `app.blade.php`.*
- [x] Layout Blade aplikasi, organism `sidebar`/`topbar` Blade beserta partial-nya, dan atom/molecule Blade yang tidak lagi direferensikan sudah dihapus. *Regresi: merge `origin/main` (PR #65-#71) ke branch ini membawa dua halaman Blade baru (Summary All Depo, Rekap Pencapaian SKU) yang ditulis setelah tiket ini selesai, sehingga layout & komponen Blade dipulihkan lagi sementara. Lihat tiket 11.*
- [x] Penanganan respons non-Inertia dari tiket #45 dihapus dari inisialisasi aplikasi Inertia. *Tidak ada penanganan di `resources/js/app.js` (sudah tidak ada sejak tiket 01); middleware `HalamanBladePenuh` juga dihapus.*
- [x] Data sidebar (struktur grup, ikon, menu yang terfilter hak akses, data pencarian) tetap tercakup tes otomatis lewat shared props Inertia.
- [x] `CLAUDE.md`, `docs/TECH.md`, dan `README.md` mendeskripsikan satu stack Vue + Inertia dan satu pustaka komponen. *Sudah diperbarui; sisa penyebutan Blade hanya root template `app.blade.php`.*
- [ ] `composer test` dan `npm run build` lulus; semua area menu diverifikasi manual di browser. *Sebagian: `php artisan test` lulus (181 tes), vitest lulus (34 tes, dengan `--pool=threads`), `npm run build` lulus sebelum penghapusan Blade (Vue tidak berubah sesudahnya). Browser (`localhost:8001`, Demo Super Admin): Beranda, Motoris Report (tiga tab), Kuota Motoris, dan navigasi sidebar kembali ke Beranda tampil tanpa error console. Belum: area menu lain, dan akses 403 untuk user tanpa hak.*

## Implementation steps

- [x] Grep pemakaian `view(`, `Route::view`, dan `x-ui.` untuk memastikan tidak ada halaman Blade tersisa sebelum menghapus apa pun. *Hasil awal: masih ada dua halaman motoris Blade. Sudah diselesaikan lewat tiket 10.*
- [x] Hapus layout Blade aplikasi, organism `sidebar`/`topbar` + `partials/`, dan atom/molecule Blade yang tidak lagi direferensikan. *Dihapus: `layouts/app.blade.php` dan seluruh `resources/views/components/ui/` (tidak ada lagi pemakai setelah layout dihapus). Ikut dihapus: `IconAtomTest` dan `TopbarPasswordInputIconTest`, karena komponen Blade-nya sudah tidak ada.*
- [x] Hapus penanganan respons non-Inertia yang ditambahkan di tiket #45. *Dihapus: middleware `app/Http/Middleware/HalamanBladePenuh.php` dan registrasinya di `bootstrap/app.php`. `resources/js/app.js` tidak punya penanganan ini.*
- [x] Ganti `SidebarCollapseGrupTest`, `SidebarCollapseFullTest`, `SidebarFlyoutTest`, `SidebarSearchTest` (sekarang merender sidebar Blade) dengan tes `assertInertia` terhadap shared props `menu`, `searchMenu`, `currentSlug`; sesuaikan atau hapus `IconAtomTest` dan `TopbarPasswordInputIconTest` bila komponen Blade-nya hilang. *Empat tes sidebar diganti dengan `tests/Feature/SidebarInertiaTest.php` (8 tes). `IconAtomTest` dan `TopbarPasswordInputIconTest` dihapus. `BreadcrumbTest` kehilangan empat tes yang merender breadcrumb Blade; logika trail-nya sudah dites di `Breadcrumb.spec.js` (Vitest), dan sisa tes slug halaman tetap ada.*
- [x] Perbarui `CLAUDE.md` (bagian dua stack rendering dan tabel komponen UI), `docs/TECH.md`, dan `README.md`. *Sudah dikerjakan: satu stack Inertia + Vue, satu pustaka `resources/js/Components/ui/`, dan tabel komponen mengikuti isi folder.*
- [ ] Jalankan `composer test`, `npm run build`, `vendor/bin/pint`, lalu verifikasi manual semua area menu di browser. *Sebagian: `vendor/bin/pint` bersih, `php artisan test` 181 tes lulus, vitest 34 tes lulus (dengan `--pool=threads`; pool default timeout di mesin ini). `npm run build` belum dijalankan ulang setelah penghapusan Blade. Browser: sebagian, lihat kriteria "composer test dan npm run build" di atas.*

## Blocked by

- 01-navigasi-inertia-ke-blade-full-reload.md
- 02-coming-soon-ke-inertia.md
- 03-role-ke-inertia.md
- 04-auth-ke-inertia.md
- 05-akun-ke-inertia.md
- 06-kehadiran-ke-inertia.md
- 07-tracking-ke-inertia.md
- 08-design-area-ke-inertia.md
- 10-motoris-ke-inertia.md
