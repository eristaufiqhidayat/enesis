# "Tanda di tanggal lain" pada drawer Riwayat

## Parent

Spec: [docs/backlog_items/051-rekap-motoris-ditandai/spec.md](../spec.md)

## What to build

Daftar tanda aktif motoris pada tanggal lain (30 hari terakhir) di bagian Tanda motoris pada drawer, dengan klik untuk berpindah ke tanggal itu.

## Acceptance criteria

- [x] `GET /tracking_sales/tanda` memuat `lainnya` (maks. 50): tanda aktif motoris itu dalam 30 hari terakhir selain tanggal yang diminta, terbaru dulu, dalam cakupan role.
- [x] Drawer menampilkan "Tanda di tanggal lain (N)" yang bisa dibuka; tiap baris memuat tanggal, kategori, dan pemberi.
- [x] Mengklik baris memindahkan tanggal drawer dan memuat ulang rute, kunjungan, dan tanda.

## Implementation steps

- [x] `TandaMotorisController@index`: `lainnya`.
- [x] `TandaMotoris.vue`: daftar dan event `pilih-tanggal`; `RuteHarianPeta.vue`: ganti tanggal.
- [x] Feature test dan Vitest.

## Blocked by

None
