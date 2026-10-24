## Parent

docs/backlog_items/018-migrasi-penuh-vue-inertia/spec.md

## What to build

Setelah semua halaman web dirender lewat Inertia, aplikasi hanya punya satu stack rendering. Layout Blade aplikasi, sidebar/topbar Blade beserta vanilla JS-nya, dan komponen Blade yang tidak lagi dipakai dihapus, sehingga perubahan sidebar/topbar berikutnya cukup dikerjakan sekali di Vue. Penanganan full reload ke halaman Blade (tiket #45) juga dibuang karena tidak ada lagi halaman Blade yang dituju.

## Acceptance criteria

- [ ] Tidak ada lagi controller/rute halaman web yang merender view Blade; satu-satunya view halaman adalah root template Inertia.
- [ ] Layout Blade aplikasi, organism `sidebar`/`topbar` Blade beserta partial-nya, dan atom/molecule Blade yang tidak lagi direferensikan sudah dihapus.
- [ ] Penanganan respons non-Inertia dari tiket #45 dihapus dari inisialisasi aplikasi Inertia.
- [ ] Data sidebar (struktur grup, ikon, menu yang terfilter hak akses, data pencarian) tetap tercakup tes otomatis lewat shared props Inertia.
- [ ] `CLAUDE.md`, `docs/TECH.md`, dan `README.md` mendeskripsikan satu stack Vue + Inertia dan satu pustaka komponen.
- [ ] `composer test` dan `npm run build` lulus; semua area menu diverifikasi manual di browser.

## Implementation steps

- [ ] Grep pemakaian `view(`, `Route::view`, dan `x-ui.` untuk memastikan tidak ada halaman Blade tersisa sebelum menghapus apa pun.
- [ ] Hapus layout Blade aplikasi, organism `sidebar`/`topbar` + `partials/`, dan atom/molecule Blade yang tidak lagi direferensikan.
- [ ] Hapus penanganan respons non-Inertia yang ditambahkan di tiket #45.
- [ ] Ganti `SidebarCollapseGrupTest`, `SidebarCollapseFullTest`, `SidebarFlyoutTest`, `SidebarSearchTest` (sekarang merender sidebar Blade) dengan tes `assertInertia` terhadap shared props `menu`, `searchMenu`, `currentSlug`; sesuaikan atau hapus `IconAtomTest` dan `TopbarPasswordInputIconTest` bila komponen Blade-nya hilang.
- [ ] Perbarui `CLAUDE.md` (bagian dua stack rendering dan tabel komponen UI), `docs/TECH.md`, dan `README.md`.
- [ ] Jalankan `composer test`, `npm run build`, `vendor/bin/pint`, lalu verifikasi manual semua area menu di browser.

## Blocked by

- 01-navigasi-inertia-ke-blade-full-reload.md
- 02-coming-soon-ke-inertia.md
- 03-role-ke-inertia.md
- 04-auth-ke-inertia.md
- 05-akun-ke-inertia.md
- 06-kehadiran-ke-inertia.md
- 07-tracking-ke-inertia.md
- 08-design-area-ke-inertia.md
