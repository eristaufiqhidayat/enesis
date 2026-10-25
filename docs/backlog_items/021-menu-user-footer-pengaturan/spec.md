# Menu User di Footer Sidebar + Halaman Pengaturan dengan Navigasi Anak

## Problem Statement

Grup sidebar "Master Data & Pengaturan" (`config/marketama.php`, section dengan `'label' => 'Master Data & Pengaturan'`) saat ini diperlakukan sama dengan 8 grup menu operasional lain: ikut di daftar scroll sidebar sebagai flyout bertingkat (Panduan, Pengumuman, lalu sub-grup Manajemen Produk / Konfigurasi / Master Data yang masing-masing berisi 5-11 menu). Padahal isinya menu administratif yang jarang dibuka dan sifatnya berbeda dari menu kerja harian, sehingga membuat daftar sidebar panjang dan tercampur.

Di sisi lain, identitas akun (nama + tombol Keluar) ada di kanan topbar ([resources/js/Components/ui/organisms/Topbar.vue:54-58](../../../resources/js/Components/ui/organisms/Topbar.vue)) tanpa menampilkan role, dan tidak ada jalan di UI bagi user yang sudah login untuk membuka `/ganti-password` sendiri (rute itu hanya dicapai lewat redirect middleware `wajib-ganti-password`).

## Solution

Pisahkan grup ini dari daftar menu utama dan gabungkan dengan menu akun:

1. Section "Master Data & Pengaturan" ditandai di config sebagai `'posisi' => 'footer'`. `Sidebar.vue` mengeluarkannya dari daftar scroll dan menyusun **tombol user** di zona `.sidebar-footer` baru (di luar area scroll, dengan garis pembatas): menampilkan nama user dan nama role (`roles.nama`). Saat sidebar dipersempit, hanya ikon avatar yang tampil.
2. Klik tombol membuka **popup ke atas** (pola `.dropdown-menu` seperti notif-bell di Topbar) berisi: header nama + role, Panduan, Pengumuman, Manajemen Produk, Konfigurasi, Master Data, Ganti Password, Keluar. Entri grup (Manajemen Produk/Konfigurasi/Master Data) menuju anak pertama yang boleh dilihat role itu. Daftar entri diturunkan dari item section di config + filter `Akses::menu()`, bukan ditulis ulang.
3. Blok user dan tombol Keluar dihapus dari Topbar.
4. Route/slug halaman lama **tidak berubah**. Halaman daftar di bawah tiga sub-grup mendapat **panel navigasi anak di kiri** (komponen organism baru, otomatis dari `menu` + `currentSlug`, dirender `AppLayout`). Di layar sempit panel menjadi baris horizontal yang bisa digulir. Panduan dan Pengumuman tidak punya panel. Halaman turunan (form "Tambah", editor peta `master_area/design_area`) tidak memakai panel.
5. Tombol footer disorot (state aktif) selama `currentSlug` ada di dalam grup ini.

Tidak ada baris tab di atas halaman: lima entri sudah tampil di popup, breadcrumb (sudah otomatis dari `menu`) menunjukkan posisi.

## User Stories

1. Sebagai admin, saya ingin menu Master Data & Pengaturan terpisah dari menu kerja harian, supaya daftar sidebar lebih pendek dan fokus.
2. Sebagai user, saya ingin melihat nama dan role akun saya di footer sidebar, supaya tahu sedang login sebagai siapa.
3. Sebagai user, saya ingin mengganti password sendiri dari menu akun tanpa menunggu diwajibkan sistem.
4. Sebagai admin yang membuka salah satu halaman Konfigurasi/Master Data/Manajemen Produk, saya ingin daftar menu saudara di sisi kiri halaman, supaya bisa berpindah antar-menu tanpa kembali ke popup.
5. Sebagai user dengan hak akses terbatas, saya hanya ingin melihat entri dan menu anak yang boleh saya buka.
6. Sebagai user di layar kecil atau sidebar yang dipersempit, saya tetap bisa mengakses menu akun dan navigasi anak.

## Implementation Decisions

- **Config:** tambah kunci `'posisi' => 'footer'` pada section "Master Data & Pengaturan" di `config/marketama.php`. Betulkan typo judul sub-grup "Konfirgurasi" menjadi "Konfigurasi".
- **Backend:** `HandleInertiaRequests` menambah nama role ke shared prop `auth.user` (lewat `Akses::role($user)`, kolom `roles.nama`). Tidak ada perubahan lain di backend; `Akses::menu()` dan `menuPencarian()` tetap dan item grup ini tetap bisa dicari.
- **Sidebar:** `Sidebar.vue` memisahkan section ber-`posisi` footer dari `navItems`. Footer + popup dibuat sebagai komponen organism/partial baru di `resources/js/Components/ui/organisms/` (mis. `SidebarUserMenu.vue`) mengikuti aturan atomic design `CLAUDE.md`. Click-outside memakai pola listener di Topbar.
- **Landing grup:** entri grup menuju anak pertama dari `children` yang tersisa setelah filter izin; grup tanpa anak tersisa tidak ditampilkan.
- **Panel kiri:** organism baru (mis. `SettingsSideNav.vue`) membaca `menu` + `currentSlug`, aktif hanya bila slug berada di salah satu dari tiga sub-grup. `AppLayout.vue` merender otomatis tanpa prop baru di halaman. Judul panel = nama sub-grup. Halaman turunan (slug yang bukan item menu, mis. `*/add`, `master_area/design_area`) tidak memunculkan panel.
- **Topbar:** hapus blok `.topbar-user` dan fungsi `keluar`; shortcut dan notif-bell tetap.
- **Teks UI** berbahasa Indonesia; ikon dari Lucide lewat atom `Icon`.

## Testing Decisions

- Feature test: shared prop `auth.user` memuat nama role; role terbatas tidak mendapat entri/anak yang tidak diizinkan di prop `menu`.
- Vitest (`*.spec.js` di samping komponen): `SidebarUserMenu` (render nama/role, buka/tutup popup, klik di luar menutup, entri grup mengarah ke anak pertama, mode collapsed) dan `SettingsSideNav` (tampil hanya untuk slug di tiga sub-grup, filter anak, state aktif).
- Verifikasi manual di browser: sidebar tidak lagi memuat grup ini; popup membuka ke atas; Keluar dan Ganti Password berfungsi; panel kiri di desktop dan mobile; sidebar collapsed; Topbar tanpa blok user.

## Out of Scope

- Mengubah route/slug, model izin (`Akses::bisa`, `roles.hak`), atau isi halaman Panduan/Pengumuman.
- Baris tab di atas halaman (tidak dipakai).
- Mengubah pencarian menu sidebar, shortcut/notif di Topbar, atau halaman Ganti Password itu sendiri.
- Foto/avatar user sungguhan (cukup ikon).

## Further Notes

- Spec ini hasil sesi grilling; keputusan awal "satu halaman dengan 5 tab" disederhanakan: lima entri ada di popup, navigasi anak di panel kiri, route lama dipertahankan agar izin per-slug, pencarian, breadcrumb, dan test yang ada tidak perlu ditulis ulang.
- Breadcrumb sudah menurunkan jejak dari `menu` + `currentSlug`, jadi otomatis menjadi "Master Data & Pengaturan / Konfigurasi / Master Area" tanpa perubahan.
