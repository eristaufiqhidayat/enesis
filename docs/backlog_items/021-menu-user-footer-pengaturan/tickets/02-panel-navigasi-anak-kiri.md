## Parent

docs/backlog_items/021-menu-user-footer-pengaturan/spec.md

## What to build

Panel navigasi anak di sisi kiri halaman daftar milik tiga sub-grup (Manajemen Produk, Konfigurasi, Master Data), supaya admin bisa berpindah antar-menu saudara tanpa kembali ke popup. Panduan dan Pengumuman tidak mendapat panel.

## Acceptance criteria

- [x] Halaman daftar slug di bawah tiga sub-grup menampilkan panel kiri berjudul nama sub-grup, berisi anak yang boleh dilihat role, dengan item aktif disorot; klik berpindah ke route slug itu (tanpa mengubah route). (Dicek Vitest: judul, daftar anak, state aktif, dan `href` tiap anak. Filter role berasal dari `Akses::menu`.)
- [x] Panduan, Pengumuman, halaman turunan (`*/add`, `master_area/design_area`), dan semua halaman di luar grup ini tidak menampilkan panel. (Dicek Vitest.)
- [x] Panel dirender otomatis oleh `AppLayout` dari `menu` + `currentSlug`, tanpa prop baru di halaman. (Dicek dengan membaca kode: tidak ada prop baru di halaman.)
- [x] Layar sempit: panel menjadi baris horizontal yang bisa digulir di atas konten. (Dicek di browser: lebar 390px lewat iframe, karena resize jendela tidak berpengaruh; `ul` berarah baris, `nav` `overflow-x: auto`, konten tidak melebar.)

## Implementation steps

- [x] Komponen baru `SettingsSideNav.vue` di `resources/js/Components/ui/organisms/` (turunan data seperti `Breadcrumb.vue`). Lookup sub-grup dipisah ke `organisms/partials/subGrup.js` supaya dipakai juga oleh `AppLayout`.
- [x] `AppLayout.vue`: render panel + konten dalam layout dua kolom bila panel berlaku; tambah CSS di `resources/css/app.css`.
- [x] Spec Vitest `SettingsSideNav.spec.js` (tampil hanya untuk tiga sub-grup, filter anak, state aktif, tidak tampil untuk Panduan/Pengumuman/slug turunan).
- [x] Verifikasi manual desktop dan mobile; jalankan `npm test`, `npm run build`. (`npm test` dan `npm run build` lulus. Desktop dicek di `/master_area`, `/produk`, dan `/panduan` (tanpa panel); mobile dicek lewat iframe 390px.)

## Blocked by

- 01-footer-menu-user-popup.md
