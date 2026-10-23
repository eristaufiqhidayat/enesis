## Parent

docs/backlog_items/009-sidebar-grup-collapsible/spec.md

## What to build

Tambah mode collapse untuk **seluruh sidebar** (bukan per grup) jadi strip ikon-saja, di stack Blade:

1. Tambah tombol toggle di `.brand` pada `resources/views/components/ui/organisms/sidebar.blade.php` (mis. `<button type="button" data-sidebar-toggle><i class="fas fa-bars"></i></button>`), ditaruh di sidebar (bukan topbar) supaya tetap terlihat saat sidebar sudah menyempit.
2. Tambah `title="{{ $item['title'] }}"` (dan untuk child: `title="{{ $child['title'] }}"`) pada tiap `.nav-link` yang sudah ada (baris 13, 21, 31) supaya ada tooltip native saat mode icon-only.
3. Tambah script di `layouts/app.blade.php` (dekat script `[data-collapse-toggle]`/`[data-dropdown-toggle]` yang sudah ada, baris 38-59): (a) saat load, baca `localStorage.getItem('sidebar-collapsed-full')` — kalau `'1'`, tambahkan class `sidebar-collapsed` ke `<body>`; (b) klik `[data-sidebar-toggle]` toggle class `sidebar-collapsed` di `<body>` dan tulis ulang ke `localStorage`.
4. Saat `sidebar-collapsed` aktif di `<body>` dan user klik item yang punya `data-collapse-toggle` (submenu/grup), hapus dulu class `sidebar-collapsed` dari `<body>` (expand sidebar) sebelum toggle submenu-nya jalan seperti biasa — reuse listener yang sudah ada, tambah satu baris di awal handler-nya.
5. CSS di `resources/css/app.css` (dekat blok `.sidebar` yang sudah ada, baris 21-43): tambah selector `body.sidebar-collapsed .sidebar` (lebar ±`4.5rem`), `body.sidebar-collapsed main` (`margin-left` menyesuaikan lebar baru), dan `body.sidebar-collapsed .sidebar .nav-link-text`, `.section-label`, `.nav-chevron` (`display: none`). Di breakpoint mobile yang sudah ada (baris 41, `max-width: 991px`), sembunyikan `[data-sidebar-toggle]` (`display: none`).

## Acceptance criteria

- [x] Tombol toggle di `.brand` sidebar mempersempit/melebarkan seluruh sidebar jadi strip ikon-saja dan kembali, tanpa reload.
- [x] State collapse tersimpan di `localStorage` key `sidebar-collapsed-full` (`'1'`/tidak ada) dan tetap terbaca setelah reload halaman penuh.
- [x] Saat mode icon-only aktif, hover ikon menu menampilkan tooltip native (`title`) berisi nama menu.
- [x] Klik item berChildren (punya submenu) saat mode icon-only aktif meng-expand sidebar kembali ke mode normal lebih dulu (bukan flyout).
- [x] Di viewport ≤991px, tombol toggle tidak tampil dan sidebar tetap pakai perilaku statis full-width yang sudah ada.
- [x] Feature test memverifikasi markup tombol `[data-sidebar-toggle]` muncul di `.brand`.

## Blocked by

None (independen dari ticket 01/02 — collapse per grup dan collapse seluruh sidebar pakai state `localStorage` terpisah; keduanya cuma berbagi file `sidebar.blade.php`/`app.blade.php`/`app.css` yang sama sehingga sebaiknya dikerjakan tidak bersamaan untuk menghindari conflict, tapi tidak saling bergantung secara fungsional).
