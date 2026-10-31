# Aturan Titik GPS Menyimpang Tunggal pada Indikasi GPS Tidak Wajar

## Problem Statement
Aturan indikasi GPS tidak wajar (spec 050) menghitung **lompatan** antar dua titik berurutan (≥ 3 km dan > 120 km/jam) dan baru memberi peringatan bila ada ≥ 5 lompatan dengan ≥ 3 di antaranya kembali ke asal. Pada data nyata, pola yang jelas lolos: Dhony Rizky Prasetya pada 22 Okt 2026 punya tiga titik GPS yang tiba-tiba berada 5,7, 7,5, dan 8,4 km dari posisi sekitarnya lalu langsung kembali (A→B→A), tetapi aturan lama hanya menghitung 4 lompatan dan 2 kembali. Penyebabnya: (1) satu titik menyimpang menghasilkan dua segmen, namun hanya satu yang dihitung "kembali"; (2) kaki masuk titik pertama (14:09) datang setelah jeda 158 menit sehingga kecepatannya kecil, dan kaki keluarnya 114 km/jam, di bawah batas 120; (3) ambang 5/3 terlalu tinggi untuk pola yang diwakili tiga titik.

## Solution
Tambahkan aturan kedua pada `AnomaliGps` yang tidak bergantung pada kecepatan maupun jeda waktu: **titik menyimpang tunggal**.

- Sebuah titik adalah *menyimpang tunggal* bila jaraknya ≥ 3 km dari titik sebelum **dan** sesudahnya, sementara kedua tetangga itu saling berdekatan (< 1 km). Artinya posisi melompat jauh lalu langsung kembali ke sekitar semula.
- Peringatan **Indikasi** muncul bila ada ≥ 3 titik menyimpang tunggal pada tanggal itu, **atau** bila aturan lompatan lama terpenuhi (salah satu cukup).
- Banner menambah kalimat "N titik GPS menyimpang jauh dari posisi sekitarnya lalu langsung kembali. Terjauh X km pada jam Y." Titik-titik itu digambar di peta sebagai lingkaran merah berpusat putih, dengan popover jarak dan jam.
- Penilaian tetap **per tanggal**; tidak ada data lintas tanggal.

## Hasil kalibrasi (22 Okt 2026, 211 motoris dengan ≥ 100 titik)
Sebaran titik menyimpang tunggal per motoris: 0 titik = 175 motoris, 1 = 15, 2 = 5, 3 = 3, 4 = 2, ≥ 5 = 11. Dengan ambang 3, 16 motoris (7,6%) tertandai; ke-11 motoris yang sudah tertandai aturan lama termasuk semuanya, dan 5 motoris bertambah: Imam Hambali (5), Hikmah Ginanjar (4), Dhony Rizky Prasetya (3), Yoyon Chariyono (3), Naning (3). Ambang 2 akan menandai 21 motoris (~10%), tetapi 1–2 titik menyimpang cukup umum sehingga berisiko salah tuduh.

## User Stories
1. Sebagai koordinator, saya ingin titik GPS yang melompat jauh lalu kembali terdeteksi walau kecepatannya tampak "wajar" karena jeda waktu panjang.
2. Sebagai koordinator, saya ingin melihat titik-titik menyimpang itu di peta, supaya saya bisa menilai sendiri.
3. Sebagai koordinator, saya tidak ingin satu atau dua titik menyimpang (kesalahan GPS biasa) memicu peringatan.
4. Sebagai admin, saya ingin ambang aturan baru bisa disesuaikan dari konfigurasi.

## Implementation Decisions
- **Config** `marketama.anomali_gps` bertambah: `menyimpang_km` 3 (jarak dari tiap tetangga), `tetangga_km` 1 (jarak maksimum antar kedua tetangga), `min_menyimpang` 3.
- **`AnomaliGps::analisis`**: setelah titik diurutkan menurut waktu, hitung titik menyimpang tunggal untuk indeks 1..n−2 (tanpa batas selisih waktu ke tetangga). `tingkat` menjadi `indikasi` bila aturan lompatan **atau** `menyimpang >= min_menyimpang` terpenuhi; `terdeteksi` (penanda mock HP) tetap lebih tinggi. Respons bertambah: `menyimpang` (jumlah), `menyimpang_terjauh` (`{ km, jam }` atau null), dan `titik_menyimpang` (maks. 100, urut waktu; tiap titik `{ lat, lng, waktu, km }`, `km` = jarak ke tetangga yang lebih dekat).
- **Vue**: `RuteHarianPeta.vue` menambah kalimat banner dan teruskan `titik_menyimpang` ke `gambarLompatan(L, lapisan, segmen, titikMenyimpang)` (`Support/rute.js`), yang menggambar `circleMarker` merah berpusat putih dengan popover. Catatan isian otomatis form Tandai motoris ikut memuat kalimat baru.
- Tidak ada perubahan skema atau endpoint.

## Testing Decisions
- Unit test `AnomaliGpsTest`: tiga titik menyimpang dengan jeda panjang (tanpa kecepatan mustahil) menjadi indikasi; dua titik tidak; dua titik berurutan yang sama-sama bergeser bukan titik menyimpang tunggal; titik di tengah jalur lurus (tetangga berjauhan) bukan titik menyimpang; selisih di bawah 3 km tidak dihitung; kasus nyata kaki masuk setelah jeda 158 menit; daftar, jarak, dan terjauh; batas 100 titik; ambang dari config. Test aturan lompatan lama diisolasi dengan menonaktifkan aturan baru lewat config.
- Feature test: endpoint `locations` menghasilkan indikasi dari titik menyimpang tunggal pada data lama dengan jeda 30 menit (tanpa lompatan) dan tidak melewati tanggal lain.
- Vitest: kalimat banner, `gambarLompatan` menggambar penanda titik menyimpang dengan popover, `RuteHarianPeta` meneruskan daftar titik.

## Out of Scope
- Mendeteksi beberapa titik berurutan yang bergeser bersamaan (relokasi), kecepatan sensor, atau akurasi GPS.
- Memeriksa ulang otomatis seluruh data historis atau menyimpan hasil (pengecekan tetap saat drawer dibuka).
- Mengubah aturan lompatan lama.
