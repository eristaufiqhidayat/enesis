# Detail Area: Drawer dari Kode/Nama

## Problem Statement

Di halaman `master_area` ([config/marketama.php](../../../config/marketama.php), tabel di [Menu/Show.vue](../../../resources/js/Pages/Menu/Show.vue)), setiap area punya belasan kolom (kode, nama, toko, sales, depo, wilayah, jarak ke depo, pembaruan terakhir). Untuk melihat gambaran satu area, user harus menggulir tabel ke kanan dan mencocokkan kolom. Jarak terdekat dan terjauh ke depo, misalnya, tidak mudah dibaca bersama lokasi wilayahnya.

## Solution

Kode dan Nama di tabel menjadi tombol teks (gaya tautan, seperti `master_depo`). Klik salah satunya membuka drawer dari kanan (memakai molecule `Modal` dengan `placement="kanan"`) berisi detail area itu. Isi drawer ditampilkan berurutan dan bisa digulir:

1. **Ringkasan**: kode, nama, total toko, nama sales.
2. **Lokasi**: provinsi, kota/kabupaten, kecamatan, kelurahan.
3. **Depo**: depo, wilayah depo, jarak terdekat dan terjauh ke depo.
4. **Pembaruan**: diupdate oleh dan diupdate pada.

Penutupan lewat tombol tutup atau Esc (sudah ditangani `Modal`).

## User Stories

1. Sebagai admin, saya ingin klik nama area dan langsung melihat detailnya tanpa menggulir tabel.
2. Sebagai admin, saya ingin melihat jarak area ke depo bersama lokasi wilayahnya.

## Implementation Decisions

- Tidak ada query baru: drawer memakai baris (`row`) yang sudah ada di tabel `master_area` (sumber `SumberMasterArea`), karena kolom yang ditampilkan sudah ada di baris itu.
- Pemicu: slot `cell-kode` dan `cell-nama` di `Menu/Show.vue` untuk tabel `master_area` (pola sama dengan `master_depo`).
- Isi drawer di `Pages/Menu/partials/DetailArea.vue`, karena hanya dipakai satu halaman. Dialog memakai molecule `Modal` yang sudah ada.
- Kolom Kode dan Nama tetap sticky (lihat konfigurasi `sticky_columns` di `master_area`).
- Peta area tidak ikut di drawer ini (di luar cakupan).

## Out of Scope

- Peta polygon area di dalam drawer.
- Tombol Edit di drawer (ubah area tetap lewat Design Area).
