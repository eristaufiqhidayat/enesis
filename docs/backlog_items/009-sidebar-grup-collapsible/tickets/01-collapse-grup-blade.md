## Parent

docs/backlog_items/009-sidebar-grup-collapsible/spec.md

## What to build

Ubah `resources/views/components/ui/organisms/sidebar.blade.php` supaya tiap grup berlabel bisa di-collapse:

1. Untuk setiap `$section` yang punya `label` (bukan grup pertama): ubah `<li class="section-label">{{ $section['label'] }}</li>` (baris 6-7) jadi elemen yang bisa diklik dengan `data-collapse-toggle="group-{{ $loop->index }}"` dan chevron (`<i class="fas fa-chevron-down nav-chevron"></i>`), mengikuti pola persis submenu toggle yang sudah ada (baris 13-16).
2. Bungkus `<ul>` daftar item grup tersebut dengan `id="group-{{ $loop->index }}"` dan class `hidden` kalau grup sedang collapsed menurut state (lihat poin 3) — script generic toggle di `layouts/app.blade.php:38-46` yang sudah menangani `[data-collapse-toggle]` otomatis bekerja tanpa perlu script tambahan.
3. Tambah script kecil (inline, di `sidebar.blade.php` sendiri atau `layouts/app.blade.php`) yang: (a) saat load, baca `localStorage.getItem('sidebar-collapsed-groups')` (JSON array berisi `label` grup yang collapsed), set class `hidden`/`is-open` sesuai state tersimpan — kecuali grup yang memuat `currentSlug` (data `active`), yang dipaksa terbuka; (b) saat toggle diklik, update `localStorage` dengan label grup yang bersangkutan ditambah/dihapus dari array tersebut.
4. Grup pertama (`label: null`) tidak diberi toggle, selalu tampil terbuka seperti sekarang.
5. Tambah/pakai ulang CSS: pastikan `.section-label` punya cursor pointer & layout flex (ikon chevron + teks) kalau grup berlabel, tanpa mengubah tampilan grup pertama yang tidak clickable.

## Acceptance criteria

- [x] Setiap grup berlabel di sidebar punya toggle (klik label collapse/expand daftar menu grup tersebut), grup pertama tidak berubah (selalu terbuka, tidak ada toggle).
- [x] State collapse per grup tersimpan di `localStorage` key `sidebar-collapsed-groups` dan tetap terbaca setelah reload halaman.
- [x] Mengunjungi URL menu yang berada di grup yang collapsed menurut `localStorage` tetap menampilkan grup itu terbuka (tidak menyembunyikan menu aktif).
- [x] Chevron grup berputar 180° saat grup terbuka (konsisten visual dengan chevron submenu yang sudah ada).
- [x] Feature test memverifikasi markup `data-collapse-toggle` muncul di semua grup berlabel dan tidak muncul di grup pertama.

## Blocked by

None (can start immediately).
