# Breadcrumb di Topnav

## Problem Statement

Aplikasi punya 119 menu di 9 grup (`config/marketama.php:7` dst.), tapi tidak ada indikator di halaman itu sendiri yang menunjukkan menu ini berada di grup mana. Satu-satunya penanda posisi saat ini adalah highlight `active`/`is-open` di sidebar (`resources/views/components/ui/organisms/sidebar.blade.php:13,21,31`, dan setara Vue-nya `Sidebar.vue`) — kalau sidebar di-scroll jauh atau user masuk dari link langsung, tidak ada cara cepat untuk tahu "saya sedang di grup apa, menu apa" tanpa mencari balik di sidebar.

Topbar (`resources/views/components/ui/organisms/topbar.blade.php`, `resources/js/Components/ui/organisms/Topbar.vue`) saat ini hanya berisi shortcut icon (Tambah Loading Barang, Tambah Barang Canvassing, Design Polygon Area) dan notifikasi — tidak ada ruang navigasi/konteks.

## Solution

Tambahkan breadcrumb (`Grup > Judul Menu`) di topbar, diturunkan dari `config('marketama.menu')` + slug halaman aktif — bukan data baru, hanya menyusun ulang data yang sudah ada:

1. **Stack Inertia/Vue:** `HandleInertiaRequests::share()` (`app/Http/Middleware/HandleInertiaRequests.php:36-44`) sudah meng-share `menu` dan `currentSlug` ke setiap halaman. Tambah komponen molecule `Breadcrumb.vue` yang menghitung trail `[grup, menu]` dari kedua props itu (cari section yang `items`/`children`-nya mengandung slug == `currentSlug`), dirender di dalam `Topbar.vue`.
2. **Stack Blade:** tambah molecule `x-ui.molecules.breadcrumb` yang menghitung trail dengan cara yang sama persis seperti `sidebar.blade.php:1` (`request()->route('slug') ?? request()->path()`) dipadukan dengan `config('marketama.menu')`, dirender di `topbar.blade.php`. Dipakai otomatis oleh semua halaman Blade (`tracking/`, `area/`, `menu.coming_soon`) karena topbar sudah termasuk dalam `layouts/app.blade.php:18`.
3. Untuk halaman yang slug-nya tidak match langsung ke `config('marketama.menu')` (mis. `menu.coming_soon` yang dirender lewat `Route::view` dengan parameter `back`, `routes/web.php:32-33`), breadcrumb memakai slug dari parameter `back` sebagai fallback supaya tetap menunjukkan grup/menu asal, bukan breadcrumb kosong.

## User Stories

1. Sebagai pengguna admin yang masuk ke sebuah menu lewat link langsung (bukan klik dari sidebar), saya ingin melihat breadcrumb di atas konten supaya langsung tahu menu ini ada di grup mana tanpa scroll sidebar.
2. Sebagai pengguna admin di halaman custom (tracking peta, design area, coming soon), saya ingin breadcrumb tetap muncul dan konsisten dengan halaman data biasa, supaya navigasi terasa seragam di seluruh aplikasi.
3. Sebagai developer yang menambah menu baru lewat `config/marketama.php`, saya ingin breadcrumb otomatis ikut benar tanpa perlu kode tambahan, karena breadcrumb murni diturunkan dari config menu yang sudah wajib diisi.

## Implementation Decisions

- Tidak ada data/struktur baru di `config/marketama.php` — breadcrumb 100% derived dari `menu` (grup → `items`/`children`) yang sudah ada, dicocokkan dengan slug halaman aktif.
- Pola pencarian slug aktif meniru yang sudah dipakai: Blade pakai `request()->route('slug') ?? request()->path()` (sama seperti `sidebar.blade.php:1`); Inertia pakai props `currentSlug` yang sudah di-share (`HandleInertiaRequests.php:41`).
- Trail breadcrumb berupa 2 level: nama grup (`section.label`, bisa `null` untuk grup pertama — kalau `null`, tampilkan hanya 1 level yaitu judul menu) dan judul menu (`item.title`/`child.title`). Untuk item dengan `children` (submenu), tampilkan 3 level: grup > parent > child.
- Komponen baru: `resources/views/components/ui/molecules/breadcrumb.blade.php` (Blade, anonymous component dengan `@props`, menerima slug override opsional lewat atribut supaya halaman seperti `menu.coming_soon` bisa pass slug `back`-nya) dan `resources/js/Components/ui/molecules/Breadcrumb.vue` (Vue, props `menu`/`currentSlug` dengan default dari `usePage().props`).
- Breadcrumb dirender di dalam `topbar.blade.php`/`Topbar.vue` (bukan di sidebar atau halaman masing-masing), konsisten dengan prinsip "Komponen UI wajib dipakai" di `CLAUDE.md` — satu komponen, dipakai semua halaman lewat layout bersama.
- Styling pakai class baru di `resources/css/app.css` (mis. `.breadcrumb`, `.breadcrumb-item`, `.breadcrumb-separator`) menyesuaikan convention class existing (`.card`, `.badge`, dst.), bukan utility Tailwind inline berulang.
- `menu.coming_soon` (`Route::view` di `routes/web.php:32-33`) perlu ditambah parameter breadcrumb (slug `back` sudah ada, dipakai ulang) — tidak perlu parameter baru.

## Testing Decisions

- Test Feature Laravel (`tests/Feature/...`) yang meng-assert breadcrumb tampil benar untuk: menu tanpa grup (`label: null`), menu dengan grup, menu dengan `children` (submenu 3 level), dan halaman `menu.coming_soon` (breadcrumb ikut slug `back`).
- Tidak perlu unit test terisolasi untuk logic pencarian slug karena logic-nya tipis (linear search di array config) dan sudah tertutup oleh test feature di atas.

## Out of Scope

- Breadcrumb dengan item yang bisa diklik untuk navigasi cepat ke grup (saat ini grup tidak punya halaman sendiri) — breadcrumb ini murni informatif, bukan navigasi tambahan.
- Mengubah struktur `config('marketama.menu')` untuk menu baru atau reorganisasi grup — di luar scope tiket ini.
- Breadcrumb dinamis berdasarkan filter/parameter tabel (mis. menampilkan nilai filter aktif) — breadcrumb ini hanya menunjukkan posisi menu di sidebar, bukan state halaman.
