# Sidebar Collapsible (Per Grup & Seluruh Sidebar)

## Problem Statement

Sidebar punya 9 grup menu (`config('marketama.menu')`, label grup di `config/marketama.php:7` dst.) dengan total 119 menu. Saat ini tiap grup dirender sebagai `<li class="section-label">` statis (Blade: `resources/views/components/ui/organisms/sidebar.blade.php:6-7`; Vue: `resources/js/Components/ui/organisms/Sidebar.vue:41`) yang hanya berupa judul, bukan toggle — semua item di semua grup selalu tampil sekaligus. Dengan 119 menu, sidebar jadi sangat panjang dan user harus scroll banyak untuk mencapai grup yang jauh di bawah, meskipun hanya butuh satu grup tertentu.

Mekanisme collapse sebenarnya sudah ada untuk level submenu (`item.children`, lihat `data-collapse-toggle` di Blade `sidebar.blade.php:13` dan `toggleSubmenu`/`manuallyToggled` di Vue `Sidebar.vue:25-33`), tapi belum diterapkan di level grup (`section.label`).

Selain itu, sidebar sendiri selalu memakai lebar penuh 260px (`.sidebar`, `app.css:21`) dan `main` selalu diberi `margin-left: 260px` (`app.css:32`) — tidak ada cara untuk memperkecil sidebar jadi mode ikon-saja supaya konten utama (tabel data yang sering lebar, lihat `DataTable`/`.data-table`) punya lebih banyak ruang di layar yang lebih kecil, tanpa harus scroll ke bawah untuk melihat kolom.

## Solution

Jadikan setiap grup (section) di sidebar collapsible, mengikuti pola collapse yang sudah ada untuk submenu — bukan mekanisme baru dari nol:

1. **Blade:** ubah `section-label` di `sidebar.blade.php` jadi elemen yang bisa diklik (`data-collapse-toggle` mengarah ke `<ul>` daftar item grup tersebut, mirip pola submenu di baris 13), dengan chevron (`.nav-chevron`) yang sudah ada class-nya di `app.css:42-43`. Script toggle collapse generik di `layouts/app.blade.php:38-46` sudah menangani `[data-collapse-toggle]` apa pun — dipakai ulang, bukan ditulis baru.
2. **Vue:** tambah state collapse per grup di `Sidebar.vue`, pola sama seperti `manuallyToggled`/`isChildOpen` (baris 9, 25-33) tapi di-key per section (misal `section.label ?? '_root'` karena grup pertama tidak berlabel dan tidak perlu collapsible — grup tanpa label selalu terbuka).
3. State collapse per grup disimpan di `localStorage` (bukan `sessionStorage` yang sudah dipakai untuk scroll position, `sidebar.blade.php`/`Sidebar.vue` keduanya punya `SCROLL_KEY`) dengan key yang konsisten antar kedua stack (mis. `sidebar-collapsed-groups`), supaya preferensi collapse user tidak hilang saat pindah antar halaman Blade dan Inertia dalam sesi yang sama maupun setelah browser ditutup.
4. Default: grup yang memuat menu aktif (`currentSlug`) otomatis terbuka saat pertama kali tanpa state tersimpan; grup lain default terbuka juga (tidak mengubah perilaku sekarang kecuali ada eksplisit collapse dari user) — supaya perubahan ini tidak mengejutkan user existing.

Selain collapse per grup di atas, tambahkan juga mode collapse untuk **seluruh sidebar** (bukan per grup) menjadi strip ikon sempit, mengikuti pola admin panel pada umumnya:

5. Tambah tombol toggle (ikon hamburger/`fa-bars`, ditaruh di `.brand` sidebar, bukan di topbar, supaya tombolnya tetap terlihat walau sidebar sudah dipersempit) yang menambah/menghapus class `sidebar-collapsed` pada elemen root (`<body>` untuk Blade, root `<nav class="sidebar">`-nya wrapper layout untuk Vue — lihat Implementation Decisions) — class ini dibaca CSS, bukan JS, untuk mengubah lebar.
6. CSS: saat `sidebar-collapsed` aktif, `.sidebar` menyempit dari `260px` jadi lebar ikon-saja (±`4.5rem`), `main`-nya ikut menyesuaikan `margin-left`, dan di dalam sidebar: `.nav-link-text`, `.section-label`, `.nav-chevron` disembunyikan (`display: none`) sehingga hanya `.nav-link-icon` yang tampil — tambahan selector turunan dari aturan yang sudah ada di `app.css:21-43`, bukan file CSS baru.
7. State mode collapse seluruh sidebar ini disimpan terpisah dari state collapse per grup (key `localStorage` sendiri, mis. `sidebar-collapsed-full`, boolean) — supaya dua preferensi (grup mana yang collapsed, dan apakah sidebar dipersempit) tidak saling menimpa.

## User Stories

1. Sebagai pengguna admin yang hanya sering memakai menu di satu-dua grup, saya ingin bisa collapse grup lain yang tidak dipakai, supaya sidebar lebih pendek dan menu yang sering dipakai lebih cepat dijangkau.
2. Sebagai pengguna admin yang sudah collapse beberapa grup, saya ingin pilihan itu tetap tersimpan saat pindah ke menu lain atau reload halaman, supaya tidak perlu collapse ulang tiap navigasi.
3. Sebagai pengguna admin yang klik link menu di dalam grup yang sedang collapsed (misal dari bookmark/notifikasi), saya ingin grup tersebut otomatis terbuka supaya saya tidak bingung kenapa menu aktif tidak terlihat di sidebar.
4. Sebagai pengguna admin yang bekerja di layar kecil atau sering butuh melihat tabel data yang lebar, saya ingin bisa mempersempit seluruh sidebar jadi strip ikon saja, supaya konten utama punya lebih banyak ruang, dan saya ingin preferensi itu tetap tersimpan saat reload atau pindah halaman.
5. Sebagai pengguna admin yang sidebar-nya sedang dalam mode ikon-saja, saya ingin tetap bisa tahu menu mana yang aktif (lewat highlight ikon) walau judul teksnya tidak tampil, supaya saya tidak kehilangan orientasi di sidebar.

## Implementation Decisions

- Grup pertama (`label: null`, `config/marketama.php:9`) tetap selalu terbuka dan tidak diberi toggle — tidak ada label untuk diklik, dan ini adalah menu "Beranda" dkk. yang paling sering dipakai.
- Key state collapse per grup pakai `section.label` (unik per grup berdasarkan data yang ada di `config/marketama.php`) disimpan sebagai array/objek di `localStorage` key `sidebar-collapsed-groups`, format sama di kedua stack supaya dibaca-tulis bergantian tanpa konflik (Blade dan Inertia keduanya berjalan di origin yang sama).
- Saat `currentSlug` berada di dalam grup yang menurut `localStorage` berstatus collapsed, grup tetap dipaksa terbuka untuk kunjungan itu (tidak menimpa preference tersimpan — hanya override tampilan saat itu), sama seperti pola `isChildOpen` existing yang auto-open submenu berisi menu aktif (`Sidebar.vue:25-29`).
- Reuse elemen visual yang sudah ada: `.nav-chevron`, `.nav-link.is-open` rotate chevron (`app.css:42-43`) — tambah class turunan untuk section-label kalau perlu selector berbeda (mis. `.section-label.is-open .nav-chevron`), tanpa menduplikasi definisi transisi/rotate yang sudah ada.
- Tidak mengubah struktur `config('marketama.menu')` — collapsible murni perilaku UI sidebar berdasarkan data section yang sudah ada.
- Toggle class `sidebar-collapsed` ditaruh di `<body>` (Blade, `layouts/app.blade.php`) dan di root elemen layout Vue (`AppLayout.vue`) — bukan di `<nav class="sidebar">` itu sendiri — supaya selector CSS `body.sidebar-collapsed .sidebar`/`main` bisa mengatur lebar sidebar sekaligus `margin-left` pada `main` dari satu class yang sama, konsisten dengan pola breakpoint responsive yang sudah ada di `app.css:41` (`@media (max-width: 991px) { .sidebar {...} main {...} }`).
- Item dengan `children` (submenu) saat sidebar dalam mode collapsed: klik ikon parent-nya tetap memperluas (expand) seluruh sidebar kembali ke mode normal lebih dulu (bukan flyout/popover di atas konten) — behavior paling sederhana yang reuse mekanisme expand yang sama dengan tombol toggle, tanpa komponen overlay baru.
- Link `title` (native tooltip HTML) ditambahkan ke `.nav-link` supaya saat mode collapsed user masih bisa hover ikon untuk melihat nama menu tanpa butuh komponen tooltip custom.
- Breakpoint mobile existing (`app.css:41`, `max-width: 991px`) sudah membuat sidebar jadi `position: static; width: 100%`; mode collapsed-icon-only ini hanya relevan di atas breakpoint tersebut — di mobile, tombol toggle disembunyikan (`display: none` via media query) supaya tidak tabrakan dengan layout statis itu.

## Testing Decisions

- Test Feature Laravel yang mem-assert markup sidebar Blade memuat atribut `data-collapse-toggle` pada tiap `section-label` berlabel, dan grup pertama (tanpa label) tidak punya toggle.
- Untuk Vue, kalau proyek belum punya test runner JS, dokumentasikan di PR perilaku yang diverifikasi manual (klik collapse per grup, reload, cek localStorage, navigasi ke menu di grup collapsed) — konsisten dengan keputusan testing di tiket-tiket sebelumnya (`006`, `007`) yang juga fallback ke verifikasi manual kalau runner JS belum ada.
- Test Feature Laravel tambahan yang mem-assert markup Blade memuat tombol toggle collapse seluruh sidebar (mis. elemen dengan `data-sidebar-toggle` atau id yang disepakati saat implementasi) di `.brand`.
- Verifikasi manual tambahan untuk mode collapsed-icon-only (didokumentasikan di PR, sama seperti verifikasi collapse per grup): toggle sidebar jadi icon-only, reload halaman (state tetap collapsed), hover ikon menampilkan `title` tooltip, klik ikon item berChildren meng-expand sidebar kembali, dan di viewport ≤991px tombol toggle tidak tampil.

## Out of Scope

- Collapse/expand semua grup sekaligus (tombol "collapse all") — tiket ini hanya per grup satu-satu.
- Mengubah urutan atau isi grup — tetap mengikuti `config('marketama.menu')` apa adanya.
- Sinkronisasi preferensi collapse antar device/user (disimpan server-side) — cukup `localStorage` per browser untuk tiket ini.
- Flyout/popover submenu saat hover ikon dalam mode collapsed-icon-only (seperti pola admin panel lain yang menampilkan submenu melayang) — tiket ini cukup expand sidebar kembali ke mode normal saat item berChildren diklik.
- Mode collapsed-icon-only untuk breakpoint mobile (≤991px) — di layar itu sidebar tetap pakai perilaku statis full-width yang sudah ada sekarang.
- Animasi/transisi lebar sidebar saat toggle (slide in/out) — cukup perubahan instan lewat class, transisi halus bisa jadi perbaikan terpisah kalau dibutuhkan.
