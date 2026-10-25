## Parent

docs/backlog_items/022-tabs-gaya-tombol/spec.md

## What to build

Tracer-bullet end-to-end: ubah styling komponen `Tabs.vue` dari gaya underline ke gaya tombol (pill/button), dan pastikan tiga halaman pemakainya (`Menu/Show.vue`, `Motoris/Report.vue`, `Motoris/RekapSku.vue`) otomatis ikut berubah tanpa perubahan di halaman itu sendiri.

## Acceptance criteria

- [ ] Tiap tab di `Tabs.vue` dirender sebagai tombol pill/rounded dengan latar (background), bukan garis bawah.
- [ ] Tab aktif punya latar solid kontras (mis. biru dengan teks putih); tab tidak aktif punya latar netral dengan hover state.
- [ ] Container tab tidak lagi memakai `border-b` gaya underline; tetap ada `overflow-x-auto` supaya tab banyak bisa digulir di layar sempit.
- [ ] Props (`tabs`, `modelValue`), event (`update:modelValue`), dan atribut aksesibilitas (`role="tablist"`, `role="tab"`, `aria-selected`) tidak berubah.
- [ ] `Tabs.spec.js` lulus (disesuaikan bila ada assertion class CSS lama).
- [ ] Verifikasi manual di browser pada salah satu halaman pemakai: tab tampak sebagai tombol, state aktif jelas, scroll horisontal tetap jalan saat browser dipersempit.

## Implementation steps

- [ ] `resources/js/Components/ui/molecules/Tabs.vue`: ganti class tombol dari gaya underline (`border-b-2`, `text-blue-600`) ke gaya pill/button (`rounded-full`/`rounded-md`, background solid untuk aktif, background netral + hover untuk tidak aktif), hapus `border-b` dari container.
- [ ] `resources/js/Components/ui/molecules/Tabs.spec.js`: sesuaikan assertion class bila merujuk ke class lama.
- [ ] Jalankan `npm test`, `npm run build`, `vendor/bin/pint`; cek manual di browser (desktop + lebar sempit) pada salah satu halaman pemakai.

## Blocked by

Tidak ada.
