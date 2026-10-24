## Parent

docs/backlog_items/010-sidebar-collapse-expand-all/spec.md

## What to build

Tambahkan satu tombol di sidebar (stack Blade) yang meng-collapse atau meng-expand semua grup menu berlabel sekaligus, reuse mekanisme collapse per grup yang sudah ada dari ticket 009 (state tersimpan di `localStorage` key `sidebar-collapsed-groups`).

- Tombol ditaruh di `.brand` sidebar, bersebelahan dengan tombol collapse-seluruh-sidebar-jadi-icon-only yang sudah ada, dengan atribut/identifier berbeda (fitur berbeda, jangan tertukar).
- Klik tombol: jika ada minimal satu grup berlabel yang sedang collapsed → expand semua grup (hapus semua label dari state tersimpan). Jika semua grup berlabel sedang terbuka → collapse semua grup (isi state dengan semua label grup berlabel yang ada di menu saat ini).
- Grup yang memuat menu aktif (`currentSlug`) tetap tampil terbuka secara visual walau ikut ditandai collapsed di state tersimpan — sama seperti override yang sudah berlaku untuk toggle per grup satu-satu.
- Tampilan tombol (ikon/`aria-expanded` atau sejenis) mencerminkan aksi yang akan terjadi berikutnya, dihitung dari state `sidebar-collapsed-groups` saat ini terhadap grup-grup yang benar-benar tampil di sidebar (bukan dari seluruh definisi config, supaya akses per role tidak salah hitung).
- Tombol disembunyikan pada breakpoint mobile (≤991px), konsisten dengan tombol collapse-sidebar-penuh yang sudah disembunyikan di breakpoint itu.
- Tidak mengubah mekanisme collapse per grup satu-satu, collapse seluruh sidebar jadi icon-only, atau collapse submenu yang sudah ada.

## Acceptance criteria

- [x] Tombol toggle baru muncul di `.brand` sidebar Blade, terpisah dari tombol collapse-sidebar-penuh yang sudah ada.
- [x] Klik tombol saat ada grup collapsed → semua grup berlabel expand; klik lagi saat semua grup terbuka → semua grup collapse (kecuali grup yang memuat menu aktif, yang tetap terlihat terbuka).
- [x] State hasil aksi ini tersimpan di `localStorage` key `sidebar-collapsed-groups` yang sudah ada (tidak membuat key baru) dan tetap konsisten setelah reload halaman.
- [x] Tombol tidak tampil di breakpoint mobile (≤991px).
- [x] Feature test Laravel memverifikasi markup tombol toggle baru muncul di `.brand`.

## Blocked by

None (can start immediately).
