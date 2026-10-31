# Nilai penjualan per kunjungan dan ringkasan harian

## Parent

Spec: [docs/backlog_items/047-nilai-penjualan-di-drawer-riwayat/spec.md](../spec.md)

## What to build

`KunjunganHarian` mengirim ringkasan penjualan per kunjungan, dan drawer menampilkannya di daftar, popover, dan ringkasan harian.

## Acceptance criteria

- [x] `penjualan = {transaksi, total, sku, qty}` per kunjungan (null bila tanpa transaksi aktif); transaksi dibatalkan tidak dihitung; beberapa transaksi dijumlahkan.
- [x] Daftar kiri dan popover memuat "Rp 35.500 · 3 jenis · 5 unit" (diawali "N transaksi ·" bila lebih dari satu).
- [x] Ringkasan harian di bawah judul daftar: jumlah kunjungan beli, tidak beli, dan total nilai.

## Implementation steps

- [x] `KunjunganHarian`: query ringkasan penjualan, `penjualan` dan `beli`.
- [x] `Support/format.js`: `ringkasanPenjualan` + spec.
- [x] `RuteHarianPeta.vue` (daftar + ringkasan harian) dan `Support/rute.js` (popover) + spec.
- [x] Feature test di `KunjunganDrawerRiwayatTest`.

## Blocked by

None
