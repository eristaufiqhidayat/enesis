## Parent

docs/backlog_items/008-breadcrumb-topnav/spec.md

## What to build

Buat molecule `resources/views/components/ui/molecules/breadcrumb.blade.php` (anonymous component, `@props` di baris pertama) yang:

1. Menerima atribut opsional `slug` (fallback ke `request()->route('slug') ?? request()->path()` kalau tidak diisi — pola yang sama dengan `sidebar.blade.php:1`).
2. Mencari slug tersebut di `config('marketama.menu')`: iterasi setiap section (`label`, `items`), lalu setiap item — kalau item punya `children`, cari juga di situ.
3. Menyusun trail:
   - Tidak ketemu: render apa-apa (komponen kosong), jangan error.
   - Ketemu di level item langsung: trail = `[section.label (kalau tidak null), item.title]`.
   - Ketemu di level child: trail = `[section.label (kalau tidak null), parent.title, child.title]`.
4. Render trail sebagai `<nav class="breadcrumb">` berisi `<span class="breadcrumb-item">` per level, dipisah `<span class="breadcrumb-separator">` (mis. `/`).
5. Tambah class CSS yang dibutuhkan (`.breadcrumb`, `.breadcrumb-item`, `.breadcrumb-separator`) di `resources/css/app.css`, menyesuaikan convention visual file itu (warna, spacing, font-size kecil khas breadcrumb).

Pasang komponen ini di `resources/views/components/ui/organisms/topbar.blade.php` (ditambahkan sebelum/berdampingan dengan shortcut icon yang sudah ada).

## Acceptance criteria

- [x] `breadcrumb.blade.php` ada dan dipakai di `topbar.blade.php`.
- [x] Mengunjungi halaman menu biasa tanpa grup (`label: null`, mis. slug `home`) menampilkan breadcrumb 1 level: "Beranda".
- [x] Mengunjungi menu di dalam grup berlabel menampilkan 2 level: "<Label Grup> / <Judul Menu>".
- [x] Mengunjungi menu yang merupakan `children` dari parent menampilkan 3 level: "<Label Grup> / <Judul Parent> / <Judul Child>".
- [x] Mengunjungi slug yang tidak match apa pun di `config('marketama.menu')` tidak menyebabkan error (breadcrumb kosong/tidak tampil).
- [x] Ada Feature test (`tests/Feature/...`) yang menutupi keempat kasus di atas dengan mem-assert isi HTML breadcrumb pada response.

## Blocked by

None (can start immediately).
