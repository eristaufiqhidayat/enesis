## Parent

docs/backlog_items/014-sidebar-header-kontras-flyout-submenu/spec.md

## What to build

Di halaman-halaman yang dirender lewat stack Blade (catch-all `MenuController@show` serta halaman custom lain yang memakai sidebar Blade), ubah cara submenu (item dengan `children`) ditampilkan: dari accordion inline (expand ke bawah, mendorong item lain) menjadi panel flyout yang muncul melayang di sebelah kanan item parent saat mouseover atau klik/Enter. Kalau sebuah child di flyout itu sendiri punya `children` lagi (struktur berlapis), flyout level berikutnya muncul lagi di sebelah kanan flyout sebelumnya (cascading ke kanan, bukan ke bawah) — dibangun generik/rekursif walau `config/marketama.php` saat ini baru 2 level.

Di breakpoint mobile (≤991px) submenu tetap tampil sebagai accordion inline seperti sekarang (flyout ke kanan tidak diaktifkan di breakpoint itu).

## Acceptance criteria

- [ ] Mouseover item ber-`children` di viewport desktop (>991px) menampilkan flyout di sebelah kanan item tersebut tanpa mendorong/menggeser item lain di sidebar.
- [ ] Klik atau tekan Enter/Space pada item ber-`children` juga membuka flyout yang sama (tidak tergantung hover saja), untuk aksesibilitas keyboard dan perangkat tanpa hover.
- [ ] Hanya satu flyout per level yang terbuka dalam satu waktu — membuka flyout sibling lain menutup flyout yang sedang terbuka di level yang sama.
- [ ] Flyout tertutup saat: klik di luar area item+panel flyout, menekan Escape, atau mouse meninggalkan area item dan panel (dengan delay singkat agar pointer sempat berpindah ke panel tanpa flyout keburu hilang).
- [ ] Item parent yang salah satu descendant-nya adalah menu aktif (`currentSlug`) diberi indikator visual aktif pada link parent-nya sendiri, walau flyout-nya sedang tertutup, supaya lokasi saat ini tetap terlihat tanpa harus membuka flyout.
- [ ] Jika sebuah item di dalam flyout punya `children` lagi, flyout level berikutnya muncul di sebelah kanan flyout saat ini (bukan dirender dalam config `marketama.php` 3 level yang sesungguhnya — cukup mekanisme render yang siap menanganinya kalau datanya ada).
- [ ] Di viewport ≤991px, submenu tetap berupa accordion inline seperti perilaku sekarang (flyout tidak aktif di breakpoint ini).
- [x] Test Feature Laravel yang mengecek markup sidebar Blade memuat atribut flyout (toggle dan panel) pada item ber-`children`.

## Blocked by

None (can start immediately).
