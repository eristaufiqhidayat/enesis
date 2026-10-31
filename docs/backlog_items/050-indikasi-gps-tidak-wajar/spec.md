# Indikasi GPS Tidak Wajar di Drawer Riwayat Lacak Motoris

## Problem Statement
Titik GPS sebagian motoris terlihat "loncat-loncat": posisinya berpindah belasan kilometer dalam hitungan menit lalu kembali lagi. Pola ini bisa berasal dari aplikasi fake GPS, tetapi tidak ada yang memberi tahu koordinator. Mereka harus menelusuri titik satu per satu di peta, dan kecurigaan baru terlihat bila kebetulan memperhatikan. Contoh nyata: pada 22 Okt 2026 Ade Suherman punya 20 lompatan mustahil (9,3–10,5 km dalam 0,7–2,3 menit), 11 di antaranya langsung kembali ke lokasi semula.

## Solution
Drawer Riwayat (Sales) menganalisis titik GPS **pada tanggal yang sedang dilihat saja** (tidak menggabungkan tanggal lain, karena motoris tidak bekerja setiap hari) dan menampilkan peringatan bila polanya mencurigakan:

- **Lompatan mustahil**: dua titik berurutan berjarak ≥ 3 km dengan kecepatan implisit > 120 km/jam.
- **Kembali ke asal**: lompatan yang titik berikutnya kembali ke dekat titik sebelum lompatan (A→B→A, jarak < 0,5 km), ciri khas berpindah-pindah antara dua lokasi.
- **Tingkat peringatan**:
  - **Terdeteksi** (merah): aplikasi HP menandai `mock_location` pada titik GPS atau kunjungan hari itu. Ini bukti langsung dari perangkat.
  - **Indikasi** (kuning): tidak ada penanda dari HP (termasuk semua data lama Marketama, yang tidak punya penanda ini), tetapi ada ≥ 5 lompatan mustahil dan ≥ 3 di antaranya kembali ke asal.
  - Aturan **titik menyimpang tunggal** (spec 052) juga menghasilkan Indikasi: ≥ 3 titik yang melompat ≥ 3 km dari kedua tetangganya lalu langsung kembali, tanpa bergantung kecepatan atau jeda waktu.
  - Di bawah ambang: tidak ada peringatan. Satu-dua lompatan adalah hal umum (pada 22 Okt, 34% motoris punya setidaknya satu).
- Peringatan memuat ringkasan ("20 lompatan, 11 kembali ke asal, terjauh 10,5 km dalam 1 menit") dan catatan bahwa ini indikasi dari pola data, bukan bukti pemalsuan.
- **Lompatan digambar di peta** sebagai garis merah putus-putus (popover: jarak, selisih waktu, kecepatan, jam).
- Tombol **Tandai motoris** (fitur tanda motoris, spec 049) terisi otomatis dengan kategori "Lokasi / GPS palsu" dan catatan berisi ringkasan, yang bisa diubah sebelum disimpan.

## Hasil kalibrasi (data 22 Okt 2026, 208 motoris aktif)
Aturan "≥ 5 lompatan dan ≥ 3 kembali ke asal" menandai 11 motoris (5%), termasuk Ade Suherman; aturan 3 dan 2 menandai 13; aturan 10 dan 5 menandai 7; "≥ 1 lompatan" menandai 71 (terlalu banyak). Persentil ke-95 persentase lompatan per motoris adalah 4,1%.

## User Stories
1. Sebagai koordinator, saya ingin diberi tahu bila titik GPS motoris pada suatu hari bergerak tidak wajar, supaya tidak perlu menelusuri peta manual.
2. Sebagai koordinator, saya ingin melihat di mana lompatannya di peta, supaya saya bisa menilai sendiri.
3. Sebagai koordinator, saya ingin peringatan yang jujur: dibedakan antara "terdeteksi" (penanda dari HP) dan "indikasi" (hanya pola), dan tidak dituduhkan sebagai bukti.
4. Sebagai koordinator, saya ingin cepat menandai motoris dari peringatan itu, dengan kategori dan catatan yang sudah terisi.
5. Sebagai admin, saya ingin ambang deteksi dapat disesuaikan dari konfigurasi tanpa mengubah kode.
6. Sebagai motoris yang tidak bersalah, saya tidak ingin satu atau dua kesalahan GPS biasa memicu peringatan.

## Implementation Decisions
- **Kelas baru `App\Support\Tracking\AnomaliGps::analisis(Collection $titik, int $kunjunganMock): ?array`**: mengurutkan titik menurut `waktu`, menghitung segmen berurutan (haversine), dan mengembalikan `null` bila tidak melewati ambang, atau `{ tingkat, lompatan: n, kembali_ke_asal: n, titik_mock: n, kunjungan_mock: n, terjauh: { km, menit, kmh, jam }, segmen: [...] }` (maks. 100 segmen untuk digambar, terjauh lebih dulu). Segmen dengan selisih waktu nol atau negatif diabaikan (tidak bisa dihitung kecepatannya).
- **Ambang di config** `marketama.anomali_gps`: `jarak_km` 3, `kecepatan_kmh` 120, `kembali_km` 0,5, `min_lompatan` 5, `min_kembali` 3.
- **`TitikGps::untuk`** menyertakan `mock` (dari `trx_tracking.mock_location`); titik lama tidak punya properti ini dan dianggap tidak mock.
- **`TrackingSalesController@locations`** menambah `anomali_gps` (null atau objek di atas) dari titik gabungan lama + HP dan jumlah kunjungan `lokasi_palsu`. Hanya endpoint Sales; endpoint Koordinator tidak berubah.
- **Vue**: `RuteHarianPeta.vue` menampilkan `Alert` (warning untuk indikasi, danger untuk terdeteksi) di atas peta, menggambar lompatan lewat `gambarLompatan` (`Support/rute.js`) di layer sendiri, dan meneruskan `saran` ({ kategori, catatan }) ke `TandaMotoris.vue` yang mengisi form otomatis saat dibuka. Halaman Riwayat Lokasi penuh tidak berubah.
- Tidak ada perubahan skema.

## Testing Decisions
- Unit test `AnomaliGpsTest`: rute normal tidak menghasilkan peringatan; satu atau dua lompatan di bawah ambang; ambang batas tepat (3 km, 120 km/jam) tidak dihitung lompatan sedangkan sedikit di atasnya ya; titik tidak berurutan diurutkan lebih dulu; deteksi kembali ke asal (A→B→A) dan non-kembali (A→B→C); "indikasi" pada 5/3 dan tidak pada 5/2; "terdeteksi" dari titik mock atau kunjungan mock walau tanpa lompatan; selisih waktu nol diabaikan; `terjauh` dan batas 100 segmen.
- Feature test: `locations` Sales memuat `anomali_gps` untuk pola lompatan (data lama), mengikuti tanggal yang diminta saja (tidak terpengaruh tanggal lain), dan `null` untuk rute normal; titik HP mock memicu "terdeteksi"; endpoint Koordinator tanpa kunci `anomali_gps`.
- Vitest: banner indikasi/terdeteksi/tanpa banner, garis lompatan digambar, `saran` diteruskan; `TandaMotoris` mengisi form dari `saran`; `gambarLompatan`.

## Out of Scope
- Mendeteksi dari akurasi GPS, kecepatan sensor, atau perbandingan dengan jaringan seluler/IP.
- Analisis lintas tanggal, skor per motoris, atau laporan rekap motoris yang terindikasi.
- Notifikasi otomatis, memblokir transaksi, atau menandai motoris tanpa tindakan koordinator.
- Peringatan di halaman Riwayat Lokasi penuh, tabel Lacak Motoris, atau aplikasi HP.
