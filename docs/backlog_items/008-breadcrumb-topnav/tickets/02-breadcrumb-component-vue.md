## Parent

docs/backlog_items/008-breadcrumb-topnav/spec.md

## What to build

Buat molecule `resources/js/Components/ui/molecules/Breadcrumb.vue` untuk stack Inertia:

1. Ambil `menu` dan `currentSlug` dari `usePage().props` (keduanya sudah di-share lewat `HandleInertiaRequests::share()` — `app/Http/Middleware/HandleInertiaRequests.php:40-41`); boleh juga diterima lewat props komponen untuk override/testing, dengan default dari `usePage().props`.
2. Pakai logic pencarian trail yang identik secara perilaku dengan versi Blade (ticket 01): cari `currentSlug` di `menu` (section → items → children), hasilkan trail 1/2/3 level sesuai aturan yang sama (section `label` null di-skip).
3. Render trail dengan markup yang sama/class CSS yang sama (`.breadcrumb`, `.breadcrumb-item`, `.breadcrumb-separator`) yang ditambahkan di ticket 01 ke `resources/css/app.css`, supaya tampilan identik di kedua stack.
4. Pasang komponen ini di `resources/js/Components/ui/organisms/Topbar.vue`, ditambahkan berdampingan dengan shortcut icon yang sudah ada.

## Acceptance criteria

- [x] `Breadcrumb.vue` ada dan dipakai di `Topbar.vue`.
- [x] Mengunjungi halaman menu lewat Inertia (`Menu/Show.vue`) menampilkan breadcrumb yang sama persis (jumlah level, teks) dengan hasil versi Blade untuk slug yang sama (home, menu berlabel, menu dengan children).
- [x] `currentSlug` yang tidak match apa pun di `menu` tidak menyebabkan error JS (breadcrumb kosong/tidak tampil, tidak ada exception di console).
- [x] Markup/class CSS breadcrumb konsisten dengan versi Blade (pakai class yang sama dari ticket 01, tidak duplikasi style baru).

## Blocked by

- Ticket 01 (breadcrumb helper & komponen Blade) — perlu logic trail dan class CSS `.breadcrumb*` yang sudah ditetapkan di sana supaya kedua stack konsisten.
