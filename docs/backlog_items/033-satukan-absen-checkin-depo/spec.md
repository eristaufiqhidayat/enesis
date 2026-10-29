# Satukan absen dan checkin depo (seperti Marketama)

> **Dibatalkan untuk aplikasi mobile** oleh `docs/backlog_items/035-pisahkan-absen-checkin-depo-area-kerja`
> (keputusan product owner sore 28 Okt 2026, sebelum PR ini sempat di-merge malamnya — lihat spec #035
> untuk kronologinya). Migration `2026_10_28_000003` (pelengkap data historis) tetap dipakai; perilaku
> endpoint di bawah ini **tidak lagi berlaku**.

## Problem Statement
Di Marketama absensi = checkin depo. Pencocokan 27 Okt 2026 menunjukkan jam masuk/keluar Lap. Absensi Sales sama persis dengan Lap. Checkin Checkout untuk 324 dari 325 sales. Aplikasi HP Enesis memisahkan dua langkah di tempat yang sama: absen (`trx_absensi`) lalu checkin depo (`trx_checkin_depo`). Akibatnya laporan absensi dan Lap. Checkin Checkout bisa berbeda bila sales melewatkan salah satunya. Keputusan product owner (28 Okt 2026): **disatukan**.

## Solution
- `POST /attendances/check-in`:
  - mencatat absen **dan** checkin depo pada jam & titik yang sama, dalam satu transaksi;
  - akun tanpa depo hanya absen;
  - radius depo tetap diperiksa (`MOBILE_ABSEN_CEK_RADIUS`);
  - respons menambah `depot_visit_id`.
- `POST /attendances/check-out` = checkout depo:
  - syarat tutup hari sama (tidak ada kunjungan / join visit terbuka; sisa canvassing sudah diserahkan → `409 return_pending`);
  - checkin depo hari itu ikut ditutup.
- Kompatibel dengan aplikasi HP versi lama:
  - `POST /depot-visits/check-in` menjawab **200** dengan `depot_visit_id` yang sudah dibuat saat absen. Checkin baru (201) hanya untuk absen sebelum aturan ini.
  - `POST /depot-visits/check-out` juga mengisi jam pulang absen.
  - Endpoint yang dipanggil kedua (absen pulang sesudah checkout depo, atau sebaliknya) menjawab 200 dengan jam yang sama. Memanggil ulang endpoint yang sama tetap `409 already_checked_out`.
- Migration `2026_10_28_000003`:
  - absen yang belum punya checkin depo dibuatkan checkin depo dari jam & titik absen;
  - checkin depo yang belum checkout ditutup dengan jam pulang absen.
- Kontrak API naik ke v1.3.0.

## Lanjutan
- Aplikasi HP (enesis-mobile): hapus langkah "Checkin depo" terpisah. Alur harian menjadi absen (= checkin depo) → terima barang → kunjungan → kembalikan sisa → absen pulang (= checkout depo).
- Koreksi absensi oleh admin (menu Absensi Sales) harus mengubah kedua tabel.

## Testing
- `tests/Feature/Mobile/AbsenCheckinDepoTest.php`: checkout depo menutup absen, absen koordinator juga checkin depo, di luar radius tidak membuat apa pun, migrasi data lama.
- `HariKerjaSalesTest` dan `AuthMobileTest` disesuaikan.
