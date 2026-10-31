# Lencana "Ditandai" di tabel Lacak Motoris

## Parent

Spec: [docs/backlog_items/051-rekap-motoris-ditandai/spec.md](../spec.md)

## What to build

Kolom "Ditandai" di tabel Tracking Last Seen Sales berisi lencana "N tanda" (tanda aktif 30 hari terakhir) yang membuka drawer Riwayat pada tanggal tanda terakhir.

## Acceptance criteria

- [x] `ditandai` = jumlah tanda aktif motoris dalam 30 hari terakhir, `ditandai_tanggal` = tanggal terbarunya; tanda dicabut dan di luar jendela tidak dihitung; hanya untuk Sales.
- [x] Sel menampilkan lencana merah "N tanda" (tanpa apa pun bila 0) dengan tooltip tanggal tanda terakhir; mengkliknya membuka drawer pada tanggal itu.
- [x] Tanpa akses ke halaman Riwayat Lokasi, lencana tampil tetapi tidak bisa diklik.

## Implementation steps

- [x] Config `tanda_motoris_hari` dan kolom `ditandai`; subquery di `SumberLastSeen`.
- [x] `Menu/Show.vue`: sel `#cell-ditandai`, pemetaan tabel → halaman riwayat eksplisit.
- [x] Feature test dan Vitest.

## Blocked by

None
