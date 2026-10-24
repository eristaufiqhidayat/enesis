# Lanjutkan Migrasi Frontend ke Vue 3 + Inertia (Hapus Stack Blade)

## Problem Statement

Aplikasi saat ini punya dua stack rendering yang hidup berdampingan. Migrasi ke Vue 3 + Inertia dimulai di commit `687c3d2` (2026-10-23) dengan niat eksplisit bertahap: *"dimulai dari halaman menu tabel (MenuController)... Halaman lain (tracking, design area) tetap Blade untuk sementara."* Sampai sekarang baru satu halaman yang dimigrasi — `Menu/Show.vue` (melayani 119 menu lewat `MenuController@show`). Semua halaman lain masih Blade di bawah `layouts/app.blade.php`:

| Area | Controller | View Blade |
| --- | --- | --- |
| Auth | `Auth\LoginController`, `Auth\GantiPasswordController` | `auth/login`, `auth/ganti-password` (layout `layouts/auth`) |
| Akun (4 menu Users) | `Admin\AkunController` (653 baris) | `akun/index`, `show`, `form`, `password`, `_sales-bawahan`, `_outlet-sales` |
| Role & hak akses | `Admin\RoleController` | `role/index`, `form`, `anggota` |
| Kehadiran | `Admin\KehadiranController` | `kehadiran/absensi`, `checkin-depo`, `_filter` |
| Tracking (peta) | `TrackingKoordinatorController`, `TrackingSalesController` | `tracking/history` (Leaflet, vanilla JS) |
| Design area (peta) | `DesignAreaController` | `area/design` (Leaflet polygon, vanilla JS) |
| Placeholder | `Route::view` | `menu/coming_soon` |

Akibatnya setiap perubahan UI bersama harus ditulis dua kali dan dijaga sinkron manual:

- Sidebar punya dua implementasi (`sidebar.blade.php` + `sidebar-nav-items.blade.php` dengan ±210 baris vanilla JS flyout/pencarian, vs `Sidebar.vue` + `SidebarNavList.vue` + `SidebarSubmenu.vue`). Komentar di kode sudah menandai sinkronisasi manual ("Sama dengan versi Blade (ticket 01)", "Key localStorage... sama dengan versi Blade").
- Backlog 009, 010, 012, 014, 017 semuanya dikerjakan dua kali (tiket Blade + tiket Vue).
- Helper interaksi (collapse, dropdown, modal, scroll sidebar) di-duplikasi: vanilla JS di `layouts/app.blade.php` vs state reaktif di `AppLayout.vue`.
- Pustaka komponen atomic design ada dua (`resources/views/components/ui/` dan `resources/js/Components/ui/`), dan Vue belum punya padanan untuk banyak atom/molecule Blade (`label`, `select`, `textarea`, `checkbox`, `radio`, `modal`, `flash`, `page-header`, `password-input`).
- `docs/TECH.md` masih mendeskripsikan stack campuran ("Vanilla JS, tanpa framework SPA" di tabel ringkasan, padahal Vue sudah dipakai).

Biaya duplikasi ini naik setiap backlog sidebar/topbar baru. Migrasi paling murah dilakukan sekarang, selagi baru satu halaman yang sudah pindah dan stack Blade belum bertambah.

## Solution

Selesaikan migrasi semua halaman web ke Inertia + Vue 3 secara bertahap per area (satu tiket per area, masing-masing bisa di-merge dan dirilis sendiri), lalu hapus stack Blade untuk halaman aplikasi: `layouts/app.blade.php`, organism `sidebar`/`topbar` Blade beserta partial-nya, atom/molecule Blade yang tidak terpakai lagi, dan tes yang merender sidebar Blade. Setelah selesai, satu-satunya file Blade yang tersisa adalah root template Inertia (`resources/views/app.blade.php`).

Endpoint JSON (tracking `locations`/`counts`, design area `options`/`areas`/store/destroy) dan API mobile (`Controllers/Mobile/*`) tidak berubah — hanya lapisan halaman yang dimigrasi.

## User Stories

1. Sebagai admin, saya ingin sidebar, topbar, dan pencarian menu berperilaku identik di semua halaman (termasuk akun, role, kehadiran, tracking, area), supaya tidak ada perbedaan halus antara halaman "lama" dan "baru".
2. Sebagai admin, saya ingin pindah antar menu tanpa full page reload di semua halaman, supaya posisi scroll sidebar, state collapse, dan flyout tidak berkedip/ter-reset.
3. Sebagai admin, saya ingin semua fitur yang ada sekarang (CRUD akun & role, atur sales bawahan koordinator, filter kehadiran, peta tracking, gambar polygon area, login & ganti password) tetap berjalan sama persis setelah migrasi.
4. Sebagai developer, saya ingin perubahan sidebar/topbar cukup dikerjakan sekali di Vue, supaya backlog UI berikutnya tidak perlu dipecah jadi tiket Blade + tiket Vue.
5. Sebagai developer, saya ingin satu pustaka komponen atomic design (Vue) saja, supaya aturan "jangan menulis ulang markup" di `CLAUDE.md` hanya perlu dijaga di satu tempat.
6. Sebagai developer, saya ingin hak akses (`hak:` middleware, tombol tambah/ubah/hapus berdasarkan hak) tetap dicek di server dan diteruskan ke Vue sebagai props, supaya migrasi tidak membuka celah otorisasi.

## Implementation Decisions

- **Persiapan sebelum migrasi**: navigasi dari halaman Inertia ke halaman yang masih Blade harus berakhir sebagai full reload (sidebar Vue memakai `<Link>` Inertia untuk semua menu, dan respons non-Inertia saat ini tidak ditangani). Penanganan ini dibuang lagi di tiket penghapusan Blade.
- **Urutan migrasi** (dari risiko terendah ke tertinggi): placeholder `coming_soon` → Role (menetapkan pola form) → Auth (membuat `PasswordInput`) → Akun; Kehadiran dan Tracking → Design Area bisa paralel setelah `coming_soon`; terakhir hapus Blade. Area peta dikerjakan belakangan karena paling bergantung pada manipulasi DOM imperatif (Leaflet).
- **Halaman password akun** menampilkan password langsung dari respons POST. Saat dimigrasi, respons ini wajib memakai history encryption Inertia dan tetap `no-store`, supaya password tidak tersimpan terbaca di `history.state` browser.
- **Controller** mengganti `return view(...)` dengan `Inertia::render('<Area>/<Halaman>', [...])`; logika query dan validasi tidak diubah. `Route::view(... 'menu.coming_soon' ...)` diganti `Route::inertia(...)`.
- **Shared props** di `HandleInertiaRequests` diperluas sesuai kebutuhan halaman yang dimigrasi: flash message (`session('status')`/sejenisnya), error validasi (bawaan Inertia), dan data user/hak yang dibutuhkan topbar. `menu`, `searchMenu`, `currentSlug` yang sudah ada tetap dipakai.
- **Form** memakai `useForm` dari `@inertiajs/vue3` (bukan `fetch` manual), supaya error validasi `422`/redirect-back bawaan Laravel langsung terpetakan ke field. Endpoint pencarian AJAX yang sudah ada (mis. `akun.*.karyawan`, `sales.cari`) tetap JSON dan dipanggil lewat `fetch`/`axios`.
- **Pustaka komponen Vue dilengkapi** sesuai kebutuhan tiap tiket (aturan ≥2 pemakaian di `CLAUDE.md` tetap berlaku): `Label`, `Select`, `Textarea`, `Checkbox`, `Radio` (atoms); `Modal`, `Flash`, `PageHeader`, `PasswordInput` (molecules). Komponen dibuat di tiket pertama yang membutuhkannya, bukan di muka.
- **Layout auth**: halaman login & ganti password memakai layout Vue terpisah (`Layouts/AuthLayout.vue`) padanan `layouts/auth.blade.php`. Middleware `wajib-ganti-password` dan redirect `guest`/`auth` tidak berubah.
- **Leaflet** tetap dimuat dari `public/vendor/leaflet` (bukan npm), diinisialisasi di `onMounted` pada elemen `ref`, dan dibersihkan (`map.remove()`) di `onUnmounted` — wajib karena Inertia tidak full reload, jadi instance peta lama harus dilepas saat pindah halaman. Inisialisasi peta yang dipakai tracking & area diekstrak ke satu composable (mis. `useLeafletMap`) di tiket Tracking dan dipakai ulang di tiket Design Area.
- **Selama migrasi berjalan**, halaman Blade dan Vue tetap hidup berdampingan; sidebar/topbar Blade tidak dihapus sampai tiket terakhir. Perubahan sidebar/topbar baru yang masuk di tengah migrasi tetap harus diterapkan di kedua stack sampai tiket penghapusan selesai.
- **Penghapusan** (tiket terakhir): hapus `layouts/app.blade.php`, `layouts/auth.blade.php`, organism `sidebar`/`topbar` Blade + `partials/`, view Blade yang sudah dimigrasi, dan atom/molecule Blade yang tidak lagi direferensikan. Perbarui `CLAUDE.md` (bagian "Two rendering stacks" dan tabel komponen), `docs/TECH.md`, dan `README.md`.

## Testing Decisions

- Setiap tiket mengganti assertion berbasis view Blade (`assertViewIs`, `assertViewHas`, `assertSee` terhadap markup — saat ini 113 kemunculan di 13 file test) pada area yang dimigrasi dengan `assertInertia(fn (AssertableInertia $page) => $page->component('...')->has(...)/->where(...))`. Assertion perilaku (redirect, status, isi database, middleware hak) dipertahankan apa adanya — itu yang membuktikan migrasi tidak mengubah perilaku.
- Tes hak akses wajib dipertahankan per halaman: user tanpa hak tetap mendapat 403/redirect, dan props yang mengatur tampil tidaknya tombol tambah/ubah/hapus mencerminkan hak user.
- Empat tes sidebar (`SidebarCollapseGrupTest`, `SidebarCollapseFullTest`, `SidebarFlyoutTest`, `SidebarSearchTest`) saat ini merender `<x-ui.organisms.sidebar />` lewat `Blade::render`. Di tiket penghapusan, tes ini diganti tes shared props Inertia (`menu`, `searchMenu`, `currentSlug` — struktur grup, ikon, hasil pencarian per hak akses). Perilaku interaktif Vue (flyout, collapse, keyboard pencarian) tidak tercakup tes otomatis karena proyek tidak punya test runner JS; diverifikasi manual di PR.
- Halaman peta (tracking, area) diverifikasi manual di browser: peta tampil, endpoint JSON terpanggil, pindah halaman lalu kembali tidak menggandakan instance peta (cek console bebas error "Map container is already initialized").

## Out of Scope

- API mobile (`app/Http/Controllers/Mobile/*`, `routes/api.php`) — bukan halaman web.
- Mengubah endpoint JSON, query, atau logika bisnis controller.
- Memindahkan Leaflet/Lucide dari `public/vendor` ke npm.
- Mendesain ulang tampilan halaman — target migrasi adalah paritas visual & perilaku.
- SSR Inertia.
- Membuat form "Tambah Loading Barang"/"Tambah Barang Canvassing" yang sebenarnya — placeholder `coming_soon` hanya dipindah ke Inertia.

## Further Notes

- Backlog 017 (ikon item daun) sedang berjalan dengan tiket Blade + Vue; selesaikan seperti biasa. Backlog sidebar/topbar berikutnya sebaiknya menunggu tiket penghapusan 018, atau dikerjakan di kedua stack bila tidak bisa menunggu.
- Commit asal migrasi: `687c3d2` (mulai Inertia), `aa93141` (pustaka komponen paralel Blade + Vue sebagai jembatan masa transisi).
