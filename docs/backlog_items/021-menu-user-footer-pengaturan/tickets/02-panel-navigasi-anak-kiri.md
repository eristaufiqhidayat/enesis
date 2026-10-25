## Parent

docs/backlog_items/021-menu-user-footer-pengaturan/spec.md

## What to build

Panel navigasi anak di sisi kiri halaman daftar milik tiga sub-grup (Manajemen Produk, Konfigurasi, Master Data), supaya admin bisa berpindah antar-menu saudara tanpa kembali ke popup. Panduan dan Pengumuman tidak mendapat panel.

## Acceptance criteria

- [ ] Halaman daftar slug di bawah tiga sub-grup menampilkan panel kiri berjudul nama sub-grup, berisi anak yang boleh dilihat role, dengan item aktif disorot; klik berpindah ke route slug itu (tanpa mengubah route).
- [ ] Panduan, Pengumuman, halaman turunan (`*/add`, `master_area/design_area`), dan semua halaman di luar grup ini tidak menampilkan panel.
- [ ] Panel dirender otomatis oleh `AppLayout` dari `menu` + `currentSlug`, tanpa prop baru di halaman.
- [ ] Layar sempit: panel menjadi baris horizontal yang bisa digulir di atas konten.

## Implementation steps

- [ ] Komponen baru `SettingsSideNav.vue` di `resources/js/Components/ui/organisms/` (turunan data seperti `Breadcrumb.vue`).
- [ ] `AppLayout.vue`: render panel + konten dalam layout dua kolom bila panel berlaku; tambah CSS di `resources/css/app.css`.
- [ ] Spec Vitest `SettingsSideNav.spec.js` (tampil hanya untuk tiga sub-grup, filter anak, state aktif, tidak tampil untuk Panduan/Pengumuman/slug turunan).
- [ ] Verifikasi manual desktop dan mobile; jalankan `npm test`, `npm run build`.

## Blocked by

- 01-footer-menu-user-popup.md
