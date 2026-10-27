# Peta Sebaran Sales

## Problem Statement
Posisi sales hanya bisa dilihat per orang (Tracking Sales History Location) atau sebagai tabel (Tracking Last Seen Sales). Tidak ada gambaran sebaran semua sales se-Indonesia dalam satu peta.

## Solution
- Halaman `/peta_sebaran_sales` (`PetaSebaranSalesController`, `Tracking/Sebaran.vue`), dibuka dari **ikon peta di kanan atas** (Topbar). Hak akses = menu `tracking_sales` (Tracking Last Seen Sales), termasuk batas cakupan role (koordinator hanya bawahannya).
- Data `GET /peta_sebaran_sales/data`: posisi GPS terakhir tiap sales aktif dari `SumberLastSeenSales` (HP `trx_tracking` atau riwayat aplikasi lama, mana yang lebih baru), plus lokasi depo (`area_depo`).
- Status kebaruan: **hari ini** (hijau), **1–7 hari** (kuning), **> 7 hari** (abu-abu), **belum ada lokasi** (hanya di daftar).
- Peta diperkecil (zoom < 9): lingkaran berangka per depo (warna = hijau bila ada sales terlihat hari ini, selain itu status terbanyak; tooltip rincian per status; klik = perbesar). Diperbesar: titik per sales (canvas) + ikon depo; klik titik = popup (depo, koordinator, terakhir terlihat, sumber, baterai, lokasi palsu, HP).
- Panel kiri: cari (nama/depo/koordinator/HP), saring depo, centang status dengan jumlahnya, daftar sales (klik = zoom ke sales). Tombol Muat ulang dan tautan ke tabel Last Seen.
- Index baru `(sales_id, waktu)` / `(koordinator_id, waktu)` di riwayat aplikasi lama untuk pencarian posisi terakhir.

## Testing
`tests/Feature/Admin/PetaSebaranSalesTest.php`: halaman & URL data; status hari ini / 1–7 hari / belum, sumber aplikasi lama, lokasi palsu, depo; koordinator hanya melihat bawahannya.
