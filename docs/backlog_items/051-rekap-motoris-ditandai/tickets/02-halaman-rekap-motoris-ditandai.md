# Halaman rekap "Motoris Ditandai" sebagai tab Lacak Motoris

## Parent

Spec: [docs/backlog_items/051-rekap-motoris-ditandai/spec.md](../spec.md)

## What to build

Halaman `motoris_ditandai` (tab ketiga grup Lacak Motoris) berisi tabel semua tanda motoris dengan pencarian, filter status, dan tautan membuka drawer.

## Acceptance criteria

- [x] Tabel memuat tanggal, motoris, depo, koordinator, kategori (label), catatan, ditandai oleh, ditandai pada (WIB), status Aktif/Dicabut, dan tautan Lihat riwayat; urut terbaru; dibatasi cakupan role.
- [x] Pencarian menurut motoris, depo, koordinator, catatan, dan pemberi; filter status Aktif / Dicabut.
- [x] Muncul sebagai tab "Ditandai" di halaman Last Seen dan Riwayat Lokasi (hanya bila akun punya hak); sidebar tetap satu entri; pencarian sidebar dan breadcrumb bekerja.
- [x] Role yang punya `lihat` pada `tracking_sales` mendapat `lihat` pada `motoris_ditandai` (migration data); tabel masuk daftar backup.

## Implementation steps

- [x] `SumberTandaMotoris`, config tabel, halaman, menu, `menu_motoris`, `tab_halaman`.
- [x] Migration hak, `DaftarTabel`.
- [x] `Menu/Show.vue`: sel riwayat dan badge status untuk tabel rekap.
- [x] Feature test dan Vitest.

## Blocked by

- 01-lencana-ditandai-di-lacak-motoris.md (pemetaan tabel ke halaman riwayat)
