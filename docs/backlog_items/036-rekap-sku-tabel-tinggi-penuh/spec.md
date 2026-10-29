# Rekap Pencapaian SKU: Tabel Tinggi Penuh

## Problem Statement

Di halaman `/rekap_pencapaian_sku` ([config/marketama.php](../../../config/marketama.php), komponen [RekapSkuTabel.vue](../../../resources/js/Pages/Motoris/partials/RekapSkuTabel.vue)) ada dua tabel berdampingan (Pencapaian ≥ target dan < target). Saat data depo atau motoris banyak, seluruh halaman ikut ter-scroll: judul halaman, tab, filter, dan header kolom tabel ikut hilang dari layar. User jadi kesulitan membaca kolom karena harus bolak-balik menggulir ke atas.

## Solution

Dua tabel dibuat mengisi sisa tinggi layar, dengan pola yang sama seperti tabel Summary All Depo (lihat `sesuaikanTinggi()` di [SummaryDepoTabel.vue](../../../resources/js/Pages/Motoris/partials/SummaryDepoTabel.vue)):

1. Area di atas tabel (judul halaman, tab, filter, tombol target) tetap di tempatnya dan tidak ikut ter-scroll.
2. Tiap tabel scroll vertikal di dalam kartunya sendiri, dengan tinggi sampai batas bawah layar.
3. Baris header tabel (`thead`) menempel di atas area scroll (`tabel-header-sticky`).
4. Di layar kecil (di bawah `lg`) kedua kartu ditumpuk, sehingga tinggi tidak dibatasi dan halaman digulir seperti biasa.

## User Stories

1. Sebagai admin, saya ingin header kolom tabel tetap terlihat saat saya menggulir daftar depo atau motoris yang panjang.
2. Sebagai admin, saya ingin judul halaman dan filter tetap terlihat tanpa harus kembali ke atas.

## Implementation Decisions

- Perubahan hanya di `RekapSkuTabel.vue`, karena tabel ini hanya dipakai di halaman Rekap Pencapaian SKU.
- Tinggi dihitung dari posisi kartu di layar dan jarak dari kartu ke bawah `<main>`, bukan dari konstanta padding, sehingga catatan "Keterangan cara hitung" di bawah tabel ikut terhitung.
- Dihitung ulang saat komponen diperbarui, saat ukuran jendela berubah, dan dihapus saat komponen dilepas.
- Pembatasan tinggi hanya aktif pada lebar `lg` (1024px) ke atas, sesuai kondisi dua kartu berdampingan.

## Out of Scope

- Sticky judul halaman (PageHeader) dan tab/filter di atas. Area itu cukup tetap di layar karena halaman tidak ikut ter-scroll.
- Mengekstrak logika tinggi penuh ke composable bersama (DataTable dan SummaryDepoTabel masih punya salinan sendiri).
- Perubahan tampilan mobile.
