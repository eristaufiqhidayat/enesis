## Parent

docs/backlog_items/014-sidebar-header-kontras-flyout-submenu/spec.md

## What to build

Terapkan perilaku flyout submenu yang sama persis seperti versi Blade (lihat tiket "Submenu flyout bertingkat — Blade") pada sidebar versi Vue/Inertia (`Sidebar.vue`), supaya kedua stack rendering konsisten. Ekstrak render item menu jadi sub-komponen rekursif agar flyout bertingkat (cascading ke kanan) bisa menangani kedalaman `children` berapa pun secara generik, bukan hardcode 2 level.

Di breakpoint mobile (≤991px), perilaku tetap accordion inline seperti sekarang.

## Acceptance criteria

- [ ] Mouseover item ber-`children` di viewport desktop (>991px) menampilkan flyout di sebelah kanan item tersebut tanpa mendorong/menggeser item lain di sidebar.
- [x] Klik item ber-`children` juga membuka flyout yang sama (tidak tergantung hover saja).
- [x] Hanya satu flyout per level yang terbuka dalam satu waktu — membuka flyout sibling lain menutup flyout yang sedang terbuka di level yang sama.
- [x] Flyout tertutup saat: klik di luar area item+panel flyout, menekan Escape, atau mouse meninggalkan area item dan panel (dengan delay singkat).
- [x] Item parent yang salah satu descendant-nya adalah menu aktif (`currentSlug`) diberi indikator visual aktif pada link parent-nya sendiri, walau flyout-nya sedang tertutup.
- [x] Jika sebuah item di dalam flyout punya `children` lagi, flyout level berikutnya muncul di sebelah kanan flyout saat ini, lewat komponen yang memanggil dirinya sendiri secara rekursif.
- [x] Di viewport ≤991px, submenu tetap berupa accordion inline seperti perilaku sekarang.
- [ ] Perilaku flyout di Vue/Inertia didokumentasikan sebagai verifikasi manual di PR (konsisten dengan ticket sebelumnya seperti 009/012 yang fallback ke verifikasi manual untuk interaksi Vue tanpa test runner JS komponen).

## Blocked by

None (can start immediately — dapat dikerjakan paralel dengan tiket Blade, karena keduanya independen meski berbagi keputusan desain yang sama di spec).
