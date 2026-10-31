# Loading Barang: Tabel Tinggi Penuh

## Problem Statement

Di halaman `/loading_barang` ([config/marketama.php](../../../config/marketama.php)) tabel transaksi loading barang ikut ter-scroll bersama seluruh halaman. Saat datanya banyak, judul halaman, pencarian, dan header kolom tabel hilang dari layar, sehingga user harus menggulir ke atas untuk membaca nama kolom.

## Solution

Pakai mekanisme `tinggi_penuh` yang sudah dipakai di Master Area dan Master Depo: tabel mengisi sisa tinggi layar, area scroll ada di dalam kartu tabel, dan `thead` menempel di atasnya (lihat `DataTable.vue`).

## User Stories

1. Sebagai admin, saya ingin header kolom Loading Barang tetap terlihat saat saya menggulir data yang panjang.
2. Sebagai admin, saya ingin judul halaman dan kolom pencarian tetap terlihat tanpa harus kembali ke atas.

## Implementation Decisions

- Perubahan hanya di konfigurasi halaman `loading_barang`: menambah `'tinggi_penuh' => true` dan `'sticky_columns' => ['kode_transaksi']`. Tidak ada perubahan komponen.
- Kolom lain tidak dibuat sticky.
- Klik Kode Transaksi membuka drawer kanan berisi semua kolom baris itu (`DetailLoadingBarang.vue`), tanpa query tambahan. Tabel `loading_barang` tidak punya rincian item, jadi drawer hanya menampilkan kolom header transaksi.
- Status Retur ditampilkan sebagai badge (`badge-columns` di `Menu/Show.vue`). Warna: "Belum Retur" = danger, "Sudah Retur" = success (peta `warnaBadge` di `Menu/Show.vue`). Nilai lain memakai default: terisi = success, kosong = `-`.

## Out of Scope

- Perubahan tampilan mobile di luar perilaku bawaan `DataTable`.
