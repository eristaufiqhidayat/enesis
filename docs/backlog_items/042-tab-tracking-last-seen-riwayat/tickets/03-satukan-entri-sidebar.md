# Satukan entri sidebar Last Seen + Riwayat Lokasi

## Parent

Spec: [docs/backlog_items/042-tab-tracking-last-seen-riwayat/spec.md](../spec.md)

## What to build

Sidebar menampilkan satu entri "Lacak Motoris" dan satu "Tracking Koordinator" menggantikan empat entri Tracking, tanpa mengubah hak per slug.

## Acceptance criteria

- [x] Sidebar admin tidak lagi memuat slug `*_history_location`; judul entri induk "Lacak Motoris" / "Tracking Koordinator".
- [x] Akun yang hanya punya hak riwayat tetap melihat entri riwayat sendiri.
- [x] Penanda aktif dan breadcrumb benar di halaman riwayat (lewat `serumpun`).
- [x] Pencarian sidebar tetap menemukan judul halaman riwayat.
- [x] Matriks hak role tetap memuat kedua slug.

## Implementation steps

- [x] `judul_sidebar` di config; `Akses::satukanTab`, `kelompokTab`, `judulMenu`; `menuPencarian` memuat `serumpun`.
- [x] `SidebarNavList.vue` dan `Breadcrumb.vue` mengenali `serumpun`; `Breadcrumb.spec.js`.
- [x] Feature test di `TabHalamanTrackingTest`.

## Blocked by

- 01-tab-penghubung-last-seen-riwayat.md
