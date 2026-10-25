## Parent

docs/backlog_items/022-tabs-gaya-tombol/spec.md

## What to build

Tracer-bullet end-to-end: ubah styling komponen `Tabs.vue` dari gaya underline ke gaya tombol (pill/button), dan pastikan tiga halaman pemakainya (`Menu/Show.vue`, `Motoris/Report.vue`, `Motoris/RekapSku.vue`) otomatis ikut berubah tanpa perubahan di halaman itu sendiri.

## Acceptance criteria

- [x] Tiap tab di `Tabs.vue` dirender sebagai tombol pill/rounded dengan latar (background), bukan garis bawah.
- [x] Tab aktif punya latar solid kontras (mis. biru dengan teks putih); tab tidak aktif punya latar netral dengan hover state.
- [x] Container tab tidak lagi memakai `border-b` gaya underline; tetap ada `overflow-x-auto` supaya tab banyak bisa digulir di layar sempit.
- [x] Props (`tabs`, `modelValue`), event (`update:modelValue`), dan atribut aksesibilitas (`role="tablist"`, `role="tab"`, `aria-selected`) tidak berubah.
- [x] `Tabs.spec.js` lulus (disesuaikan bila ada assertion class CSS lama).
- [x] Verifikasi manual di browser pada salah satu halaman pemakai: tab tampak sebagai tombol, state aktif jelas, scroll horisontal tetap jalan saat browser dipersempit.

## Implementation steps

- [x] `resources/js/Components/ui/molecules/Tabs.vue`: ganti class tombol dari gaya underline (`border-b-2`, `text-blue-600`) ke gaya pill/button (`rounded-full`/`rounded-md`, background solid untuk aktif, background netral + hover untuk tidak aktif), hapus `border-b` dari container. Styling diimplementasikan lewat class komponen baru `.tab-pill`/`.tab-pill.active` di `app.css`, bukan utility Tailwind inline, sesuai aturan `docs/STYLE_GUIDE.md`.
- [ ] `resources/js/Components/ui/molecules/Tabs.spec.js`: sesuaikan assertion class bila merujuk ke class lama. — tidak perlu diubah, spec yang ada tidak mengecek class CSS (hanya role/aria-selected/event).
- [x] Jalankan `npm test`, `npm run build`, `vendor/bin/pint`; cek manual di browser (desktop + lebar sempit) pada salah satu halaman pemakai.

## Blocked by

Tidak ada.
