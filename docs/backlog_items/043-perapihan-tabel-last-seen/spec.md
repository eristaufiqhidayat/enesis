# Rapikan Tabel Tracking Last Seen Sales / Koordinator

## Problem Statement
Tabel Tracking Last Seen Sales dan Koordinator (`SumberLastSeen`, config `tracking_last_seen_sales` / `tracking_last_seen_koordinator`) menampilkan 11 kolom, sebagian kurang membantu admin yang ingin cepat tahu siapa yang tidak terlihat:

- **Latitude** dan **Longitude** berupa angka mentah yang tidak bisa dibaca manusia.
- **Baterai** dan **Sumber** ("Aplikasi HP" / "Aplikasi lama") lebih relevan untuk developer. Data aplikasi lama hanya sampai 22 Okt 2026, jadi lama-kelamaan kolom Sumber selalu berisi "Aplikasi HP".
- Kebaruan hanya berupa teks "Sejak" ("3 jam yang lalu"), padahal Peta Sebaran Sales sudah punya status berwarna (hari ini / minggu ini / lama / belum). Memindai puluhan baris teks lambat.
- Tidak ada cara menjawab "siapa yang tidak terlihat hari ini?" selain membaca semua baris.

## Solution
1. **Satu kolom Terakhir Terlihat**: menggantikan tiga kolom yang saling mengulang (Terakhir Terlihat, Sejak, Status). Isinya badge berwarna menurut kebaruan dengan teks relatif ("1 jam yang lalu"); waktu pastinya muncul sebagai tooltip. Warna memakai batas yang sama dengan Peta Sebaran Sales (awal hari ini, 7 hari sebelumnya): "Hari ini" (hijau), "1–7 hari lalu" (kuning), "Lebih dari 7 hari" (merah), "Belum ada data" (abu-abu, teks "Belum ada data").
2. **Kolom Lokasi** menggantikan Latitude dan Longitude: tombol "Lihat peta" yang membuka drawer dari kanan berisi peta (Leaflet, komponen `DetailDepoPeta` dengan ikon motoris) dengan penanda posisi terakhir dan popup waktu terakhir terlihat; tanpa membuka Google Maps. Kosong bila belum ada koordinat.
3. **Sembunyikan Baterai dan Sumber** dari tabel. Datanya tetap dihitung di `SumberLastSeen::ubahBaris`, karena Peta Sebaran Sales masih memakainya.
4. **Filter Status** (dropdown "Semua status") di header tabel, dengan empat pilihan yang sama dengan badge. Filter dijalankan di SQL, bukan di halaman yang sudah dimuat, supaya benar lintas halaman paginasi.

Perubahan berlaku untuk sales dan koordinator sekaligus karena memakai `SumberLastSeen` yang sama.

## User Stories
1. Sebagai admin, saya ingin melihat badge kebaruan berwarna, supaya sales yang sudah lama tidak terlihat langsung menonjol.
2. Sebagai admin, saya ingin memfilter "Lebih dari 7 hari" atau "Belum ada data", supaya saya hanya melihat sales yang bermasalah.
3. Sebagai admin, saya ingin mengklik "Lihat peta" di baris sales dan langsung melihat posisinya, tanpa menyalin koordinat.
4. Sebagai admin, saya tidak ingin kolom teknis (baterai, sumber data) memenuhi tabel.
5. Sebagai admin, saya ingin perilaku yang sama di tabel Koordinator.
6. Sebagai pengguna Peta Sebaran Sales, saya ingin peta tetap menampilkan baterai dan sumber seperti sebelumnya.

## Implementation Decisions
- **Config** (`config/marketama.php`): kolom kedua tabel menjadi `nama, telepon, depo, koordinator, terakhir_terlihat, lokasi, riwayat, lokasi_palsu` (`sejak` dan `status` tetap ada di objek baris untuk badge, filter, dan peta). `latitude`, `longitude`, `baterai`, `sumber` dihapus dari `columns` (tetap ada di objek baris).
- **`SumberLastSeen::ubahBaris`** menambah `status` (label di atas) dengan batas yang sama dengan `PetaSebaranSalesController::data`. `latitude`/`longitude` tetap ada untuk peta dan untuk tautan lokasi.
- **Interface baru** `App\Support\Menu\SumberBerstatus extends SumberTabel`: `pilihanStatus(): array` (kunci => label) dan `batasiStatus(Builder, string $status): void`. `SumberLastSeen` mengimplementasikannya.
- **`batasiStatus`**: waktu efektif = yang lebih baru antara titik HP (`trx_tracking.recorded_at`, UTC) dan titik lama (`tracking_*_lokasi.waktu`, zona aplikasi). Karena itu "terlihat sejak X" = ada titik HP >= X (dalam UTC) ATAU ada titik lama >= X (dalam zona aplikasi), dan "tidak terlihat sejak X" = tidak ada keduanya. Kunci: `hari_ini`, `minggu` (terlihat sejak 7 hari lalu tapi tidak sejak awal hari ini), `lama` (punya data tapi tidak sejak 7 hari lalu), `belum` (tidak punya data sama sekali). Dengan `EXISTS`, tanpa konversi zona waktu di SQL.
- **`MenuController`**: `filter` bertambah `status` (string dari `?status=`); `muatTabel` memanggil `batasiStatus` bila sumber `SumberBerstatus` dan kuncinya valid (kunci tak dikenal diabaikan); payload tabel bertambah `pilihan_status`.
- **Vue** (`Menu/Show.vue`): `Select` "Semua status" di header (komponen `Select` yang sudah ada) bila tabel punya `pilihan_status`, ikut `useAmbilData`/`submitSearch`/`PROPS_MENU`; slot `#cell-lokasi` untuk dua tabel Last Seen berisi tautan; `warnaBadge` dan `badge-columns` untuk kolom `status`.
- Tidak ada perubahan skema atau data.

## Testing Decisions
- Feature test: tiap status (`hari_ini`, `minggu`, `lama`, `belum`) menyaring baris yang benar, termasuk sales yang hanya punya data HP, hanya data lama, dan keduanya (HP lebih lama dari lama dan sebaliknya); kunci tak dikenal tidak menyaring; label `status` per baris benar; kolom hasil config tidak lagi memuat latitude/longitude/baterai/sumber; Peta Sebaran Sales masih mengirim baterai dan sumber.
- Verifikasi manual di browser: badge, filter, tautan "Lihat peta".

## Out of Scope
- Filter "tidak terlihat > N jam" dengan angka bebas (status per hari mengikuti Peta Sebaran Sales).
- Kolom alamat/kelurahan dari koordinat.
- Mengubah Peta Sebaran Sales atau urutan baris tabel.
