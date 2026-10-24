## Parent

docs/backlog_items/013-ganti-ikon-lucide/spec.md

## What to build

Rombak Icon atom di kedua stack supaya bisa merender SVG Lucide by name, sebagai fondasi sebelum titik-titik pemakaian lain (sidebar, topbar, dst.) dipindah ke sana. Unduh file SVG Lucide yang dibutuhkan untuk kebutuhan awal (minimal: `home`, `chevron-down`, `eye`, `eye-off` — ikon lain ditambahkan di tiket-tiket berikutnya sesuai kebutuhan) ke `public/vendor/lucide/<name>.svg`, self-hosted seperti `public/vendor/fontawesome/`, bukan lewat npm.

- `resources/views/components/ui/atoms/icon.blade.php`: baca `public/vendor/lucide/{name}.svg` dan echo sebagai HTML mentah, dengan `$attributes->merge()` diterapkan ke elemen `<svg>` root.
- `resources/js/Components/ui/atoms/Icon.vue`: import SVG lewat suffix `?raw` Vite (atau pendekatan setara tanpa paket npm icon) dan render lewat `v-html`, dengan atribut fallthrough standar Vue tetap jatuh ke elemen root.

Icon atom lama (`<i class="fas fa-{name}">`) tidak dipakai lagi setelah tiket ini — tapi pemakai lain (sidebar, topbar, dst.) masih memakai `<i class="fas fa-*">` mentah sampai tiket-tiket berikutnya memindahkannya.

## Acceptance criteria

- [x] `public/vendor/lucide/home.svg`, `chevron-down.svg`, `eye.svg`, `eye-off.svg` ada sebagai file SVG individual.
- [x] `<x-ui.atoms.icon name="home" />` merender elemen `<svg>` (bukan `<i class="fas fa-home">`).
- [x] `<x-ui.atoms.icon name="home" class="foo" />` menghasilkan elemen `<svg class="foo">` (atribut tambahan ter-merge ke root).
- [x] `<Icon name="home" />` (Vue) merender elemen `<svg>` yang sama isinya dengan file `public/vendor/lucide/home.svg`.
- [x] Feature test baru (`tests/Feature/IconAtomTest.php` atau serupa, pola `Blade::render`) meng-assert poin di atas untuk sisi Blade.

## Blocked by

None (can start immediately).
