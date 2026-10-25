## Parent

docs/backlog_items/021-menu-user-footer-pengaturan/spec.md

## What to build

Tracer-bullet end-to-end: grup "Master Data & Pengaturan" keluar dari daftar scroll sidebar dan diganti tombol user di footer sidebar dengan popup menu. Blok user/Keluar di Topbar dihapus. Setelah tiket ini, semua halaman grup tetap bisa dicapai lewat popup.

## Acceptance criteria

- [x] Section "Master Data & Pengaturan" tidak lagi muncul di daftar scroll sidebar; zona `.sidebar-footer` baru (di luar area scroll, garis pembatas di atas) menampilkan tombol dengan nama user dan nama role (`roles.nama`). (Dicek di browser desktop.)
- [x] Klik tombol membuka popup ke atas berisi header nama + role, Panduan, Pengumuman, Manajemen Produk, Konfigurasi, Master Data, Ganti Password (`/ganti-password`), Keluar. Klik di luar menutup popup.
- [x] Entri grup menuju anak pertama yang boleh dilihat role; grup tanpa anak yang diizinkan tidak tampil. Entri berasal dari config + `Akses::menu()`, bukan daftar tertulis di Vue.
- [x] Keluar berfungsi (POST `/logout`); blok `.topbar-user` dan tombol Keluar hilang dari Topbar.
- [x] Pencarian menu sidebar tetap menemukan item grup ini. (Dicek di browser: "Kota Kabupaten" muncul.)
- [x] Typo "Konfirgurasi" di config dibetulkan.

## Implementation steps

- [x] `config/marketama.php`: tambah `'posisi' => 'footer'` pada section "Master Data & Pengaturan"; betulkan judul "Konfirgurasi".
- [x] `app/Http/Middleware/HandleInertiaRequests.php`: tambah nama role ke `auth.user` (via `Akses::role($user)->nama`).
- [x] Komponen baru `SidebarUserMenu.vue` di `resources/js/Components/ui/organisms/` (tombol + popup, pola click-outside dari Topbar); pakai atom `Icon`, `Button` bila cocok.
- [x] `Sidebar.vue`: pisahkan section `posisi === 'footer'` dari `navItems`, render `.sidebar-footer` di bawah `.sidebar-scroll`; tambah CSS di `resources/css/app.css`.
- [x] `Topbar.vue`: hapus blok user dan `keluar`.
- [x] Test: feature test `auth.user` memuat role & `menu` terfilter untuk role terbatas; spec Vitest `SidebarUserMenu.spec.js`.
- [ ] Jalankan `php artisan test`, `npm test`, `npm run build`, `vendor/bin/pint`; cek manual di browser. (Empat perintah otomatis sudah lulus. Cek browser desktop sudah dilakukan; cek mobile dan sidebar yang dipersempit belum lengkap, karena resize jendela tidak berpengaruh di browser.)

## Blocked by

Tidak ada.
