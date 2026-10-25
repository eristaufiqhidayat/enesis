# Tampilan Tab Menu Jadi Gaya Tombol

## Problem Statement

Komponen `Tabs` ([resources/js/Components/ui/molecules/Tabs.vue](../../../resources/js/Components/ui/molecules/Tabs.vue)) saat ini menampilkan daftar tab horisontal di atas halaman sebagai garis bawah (`border-b-2`) yang menyorot tab aktif dengan warna teks biru, mengikuti gaya tab klasik (seperti tab browser/dokumen). Tab ini dipakai di tiga halaman: [Menu/Show.vue](../../../resources/js/Pages/Menu/Show.vue), [Motoris/Report.vue](../../../resources/js/Pages/Motoris/Report.vue), dan [Motoris/RekapSku.vue](../../../resources/js/Pages/Motoris/RekapSku.vue).

Gaya garis bawah ini kurang terlihat sebagai elemen yang bisa diklik (afforance rendah), terutama di layar kecil atau saat jumlah tab banyak dan harus discroll horisontal. User ingin tab ini tampil sebagai tombol (pill/button) yang jelas punya latar (background) dan bentuk tombol, konsisten dengan komponen `Button` atom lain di aplikasi.

## Solution

Ubah markup dan class Tailwind di `Tabs.vue` dari gaya underline menjadi gaya tombol:

1. Tiap tab dirender sebagai tombol berbentuk pill/rounded dengan latar (background) yang jelas, bukan garis bawah.
2. Tab aktif punya latar solid (warna brand, mis. biru) dengan teks kontras (putih), tab tidak aktif punya latar netral (abu-abu muda) dengan teks gelap dan hover state.
3. Container tab tidak lagi memakai garis pemisah bawah (`border-b`) khas gaya underline; cukup jarak (gap) antar tombol dan padding di sekeliling baris tab.
4. Perilaku lain (scroll horisontal saat tab banyak/sempit, `role="tablist"`/`role="tab"`/`aria-selected`, event `update:modelValue`) tidak berubah — hanya styling visual yang diganti.
5. Karena ini komponen molecule bersama (dipakai ≥2 tempat sesuai aturan atomic design di `CLAUDE.md`), perubahan cukup dilakukan di satu file `Tabs.vue` dan otomatis berlaku di tiga halaman pemakainya.

## User Stories

1. Sebagai user, saya ingin tab menu di atas halaman terlihat jelas sebagai tombol yang bisa diklik, supaya saya tidak ragu-ragu saat berpindah tab.
2. Sebagai user di layar kecil, saya tetap bisa menggulir tab secara horisontal dan melihat tab mana yang aktif dengan jelas lewat warna latar tombol.

## Implementation Decisions

- Hanya mengubah `resources/js/Components/ui/molecules/Tabs.vue` (markup/class), tidak mengubah API props (`tabs`, `modelValue`) atau event (`update:modelValue`).
- Gunakan token warna Tailwind yang sudah dipakai komponen lain di aplikasi (mis. biru untuk state aktif, abu-abu untuk netral) supaya konsisten dengan `Button` atom dan tidak menambah token warna baru.
- Tetap pertahankan `overflow-x-auto` pada container supaya tab banyak tetap bisa digulir di layar sempit.
- Tidak mengubah tiga halaman pemakai (`Menu/Show.vue`, `Motoris/Report.vue`, `Motoris/RekapSku.vue`) — mereka hanya memanggil `<Tabs>` tanpa tahu detail styling internal.

## Testing Decisions

- Update `Tabs.spec.js` bila ada assertion yang bergantung pada class CSS lama (mis. cek `border-b-2` atau warna teks aktif) — sesuaikan ke class baru.
- Verifikasi manual di browser: buka salah satu halaman pemakai (mis. `Menu/Show.vue` dengan tab, atau `Motoris/Report.vue`), pastikan tab tampil sebagai tombol, state aktif jelas, hover state tidak aktif berfungsi, dan scroll horisontal tetap jalan saat lebar browser dipersempit.

## Out of Scope

- Mengubah komponen `Tabs` menjadi dropdown/select di layar sempit (tetap baris horisontal yang bisa digulir).
- Mengubah halaman mana yang memakai tab atau menambah/menghapus tab di halaman yang sudah ada.
- Mengubah komponen `Button` atom itu sendiri.
