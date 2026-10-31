# Nilai Penjualan di Drawer Riwayat Lacak Motoris

## Problem Statement
Drawer Riwayat (spec 044–046) hanya menyebut sebuah kunjungan "Beli" atau "Tidak beli". Padahal tiap kunjungan yang menghasilkan penjualan punya transaksi di `trx_penjualan` (nilai, jumlah jenis barang, kuantitas), sehingga admin tidak bisa membedakan kunjungan bernilai besar dari yang kecil, dan tidak ada ringkasan produktivitas motoris pada hari itu.

## Solution
1. **Nilai transaksi per kunjungan.** Pada kunjungan yang beli, daftar kiri dan popover menampilkan ringkasan penjualan: "Rp 35.500 · 3 jenis · 5 unit". Bila satu kunjungan punya lebih dari satu transaksi, ringkasan diawali "2 transaksi ·" dan angkanya dijumlahkan.
2. **Ringkasan harian** di bawah judul daftar: "38 beli · 40 tidak beli · Rp 1.250.000", dihitung dari kunjungan yang ditampilkan (transaksi dibatalkan tidak dihitung).
3. Rincian barang per transaksi **tidak** termasuk (lihat Out of Scope).

## User Stories
1. Sebagai admin, saya ingin melihat nilai penjualan tiap kunjungan, supaya kunjungan bernilai besar dan kecil mudah dibedakan.
2. Sebagai admin, saya ingin ringkasan hari itu (berapa kunjungan beli, berapa tidak, total nilai), supaya produktivitas motoris terbaca tanpa menghitung manual.
3. Sebagai admin, saya ingin kunjungan dengan beberapa transaksi tetap terbaca benar (jumlah transaksi dan total gabungan).
4. Sebagai admin, saya tidak ingin transaksi yang dibatalkan ikut terhitung.

## Implementation Decisions
- **`KunjunganHarian::untuk`**: satu query tambahan ke `trx_penjualan` (`dibatalkan_at IS NULL`, `WHERE kunjungan_id IN (…)`, `GROUP BY kunjungan_id`) menghasilkan `COUNT(*)`, `SUM(total)`, `SUM(jumlah_sku)`, dan `SUM(total_qty)`. Tiap kunjungan mendapat `penjualan: { transaksi, total, sku, qty }` atau `null`; `beli` = `penjualan !== null`. Subquery `jumlah_transaksi` yang lama diganti query tambahan ini (satu query, bukan satu per baris).
- **Format**: helper `ringkasanPenjualan(penjualan)` di `Support/format.js` (rupiah lewat `rupiah`, unit lewat `angkaRingkas`) dipakai bersama daftar (`RuteHarianPeta.vue`) dan popover (`Support/rute.js`).
- **Ringkasan harian** dihitung di `RuteHarianPeta.vue` dari array `kunjungan` (tanpa perubahan endpoint).
- Tidak ada perubahan skema atau hak akses.

## Testing Decisions
- Feature test (`KunjunganDrawerRiwayatTest`): `penjualan` berisi transaksi, total, sku, qty yang benar; null bila tanpa penjualan atau penjualannya dibatalkan; kunjungan dengan dua transaksi dijumlahkan; `beli` mengikuti `penjualan`.
- Vitest: `format.spec.js` untuk `ringkasanPenjualan`; `rute.spec.js` untuk popover; `RuteHarianPeta.spec.js` untuk ringkasan di daftar dan ringkasan harian.

## Out of Scope
- Rincian barang per transaksi (produk, qty, harga) dan kode transaksi.
- Poin, diskon, dan status effective call.
- Ringkasan harian lintas hari atau lintas motoris.
