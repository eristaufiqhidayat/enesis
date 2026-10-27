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

## Revisi 27 Okt 2026: polygon Master Area + nama sales
Permintaan: peta menampilkan **polygon Master Area se-Indonesia** dan **nama sales di dalam tiap area** saat diperbesar (posisi GPS jadi lapisan tambahan).

- Kolom kotak batas `area_polygon.min_lng/min_lat/max_lng/max_lat` (+ index `min_lat, max_lat`), diisi migration untuk semua area dan oleh `AreaMk::sinkron` setiap area disimpan.
- `GET /peta_sebaran_sales/data`: ringkasan per depo (jumlah area, area tanpa sales, sales aktif yang ditugaskan di area depo itu, titik tampil, kotak batas) + daftar sales (dengan jumlah area).
- `GET /peta_sebaran_sales/area?bbox=` (area di layar, maks. 2.500, selebihnya diminta memperbesar) atau `?karyawan_id=` (semua area satu sales): polygon + sales yang ditugaskan (aktif/tidak).
- Cakupan role: selain "semua", area dibatasi ke depo / penugasan sales dalam cakupan.
- Tampilan:
  - zoom < 11: lingkaran per depo berisi jumlah area, klik untuk memperbesar;
  - zoom ≥ 11: polygon area di layar (biru = ada sales aktif, kuning = hanya penugasan tidak aktif, abu-abu putus-putus = belum ada sales), tooltip & popup area (kode Master Area, depo, wilayah, daftar sales, tautan Master Area / Design Area);
  - zoom ≥ 13: nama sales (maks. 2 + "+n") di tengah area.
- Panel: saring depo, centang "posisi GPS terakhir", daftar sales; klik sales = semua areanya disorot merah dan peta diperbesar ke sana.
- Uji kecepatan (PostgreSQL 16, 13.254 area + 3.300 penugasan): data 0,05 dtk; area per layar 0,03 dtk (696 area, 191 KB); isi kotak batas saat migrate ±7 dtk.

