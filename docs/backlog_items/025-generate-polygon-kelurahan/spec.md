# Generate Polygon Kelurahan

## Problem Statement

Marketama punya halaman `master_area/kelurahan_polygon` untuk membuat area massal, 1 area = 1 kelurahan. Cara kerjanya (dianalisa dari kode JS halaman, lihat project doc `claude/menu-master-area-kelurahan-polygon.md`):

1. Daftar kelurahan dicari ke **Overpass API (OpenStreetMap)** langsung dari browser, jadi nama kelurahan berasal dari OSM, bukan dari master kelurahan.
2. Polygon tiap kelurahan dicari satu per satu ke **Nominatim** lewat proxy backend, dengan 5 variasi kueri teks bebas. Jedanya 2 detik per kelurahan dan sampai 40 detik saat kena rate limit, jadi satu kabupaten bisa memakan 10–20+ menit.
3. Kecamatan ditebak dari `display_name` Nominatim.
4. MultiPolygon dipotong: hanya polygon yang titiknya terbanyak yang disimpan, dan lubang dibuang.
5. Tidak ada cek duplikat, jadi generate dua kali menghasilkan area ganda.
6. Ada bug JS: setelah simpan, kode memanggil `setOptions` milik Google Maps pada polygon Leaflet.

## Solution

Halaman baru di URL yang sama, `master_area/kelurahan_polygon` (`GenerateKelurahanController` dan `Area/GenerateKelurahan.vue`).

- **Sumber batas resmi**: dataset [cahyadsn/wilayah_boundaries](https://github.com/cahyadsn/wilayah_boundaries) (MIT, Kepmendagri 300.2.2-2430/2025), satu berkas SQL per kota (±0,1–3 MB). Berkas diambil **sekali per kota di server** (`App\Support\Wilayah\BatasWilayah::pastikanKota`), di-parse, lalu disimpan di tabel baru `batas_wilayah` (GeoJSON MultiPolygon `[lng, lat]`, centroid, luas km², jumlah titik). Kota yang sama berikutnya langsung tampil tanpa internet.
- **Cocok lewat kode Kemendagri**: `kelurahan.kode` (81.876 dari 84.091 kelurahan berkode) dicocokkan ke `batas_wilayah.kode`. Untuk Jakarta Pusat dan Kep. Seribu cocok 100%. Kelurahan tanpa kode (hasil "tambahan" geocode Marketama) ditandai **Tanpa batas resmi** dan diarahkan ke Design Area untuk digambar manual.
- **Daftar diambil dari master Enesis**, bukan OSM: kota/kecamatan/kelurahan dari tabel `kota_kabupaten`/`kecamatan`/`kelurahan`.
- **Geometri utuh**: kolom baru `area_polygon.geometri` menyimpan MultiPolygon lengkap (semua pulau dan lubang). Kolom lama `polygon` tetap satu cincin agar kompatibel, tetapi dipilih berdasarkan **luas terbesar**, bukan jumlah titik. Design Area menggambar `geometri` bila ada.
- **Anti duplikat**: kelurahan yang sudah punya area ditandai **Sudah punya area**, tidak bisa dipilih, dan dilewati server. Area dikenali lewat `village_id`, `kode_kelurahan`, atau (untuk area lama Marketama) kecocokan nama kota+kecamatan+kelurahan tanpa beda huruf besar/kecil.
- **Server yang menentukan geometri**: browser hanya mengirim `warehouse_id` + `kelurahan_ids`, lalu server mengambil batas dari `batas_wilayah`. Polygon dari browser tidak dipercaya.
- **Kode area** `AREA-YYYYMMDD-NNNN` dipindah ke `App\Support\Area\KodeArea`, dipakai bersama Design Area, dihitung di dalam transaksi, dan tetap benar setelah lewat 9999.
- UI: pilih Depo (provinsi terisi otomatis), Provinsi, Kota, dan Kecamatan opsional, lalu **Tampilkan Kelurahan**.
  - Peta menampilkan semua kelurahan sekaligus, diwarnai per kecamatan; klik polygon untuk memilih atau membatalkan, dan tooltip menunjukkan luas serta jarak ke depo.
  - Daftar punya pencarian, saringan status, dan Pilih Semua yang siap. Ringkasan menunjukkan total, siap, sudah ada, tanpa batas, serta dipilih + total km².
  - Tombol **Buat N Area** muncul bila user punya hak `tambah` di `master_area`.
- Command `php artisan wilayah:impor-batas --provinsi=31 | --kota=3171 | --semua [--dir=...] [--paksa]` untuk mengisi di muka, termasuk dari folder lokal hasil `git clone` bila server tanpa internet (`BATAS_WILAYAH_DIR`).

## Di luar cakupan

- Pencarian ke OSM/Nominatim untuk kelurahan tanpa kode. Data "tambahan" Marketama ini kebanyakan nama geocode ganda (mis. "East Cempaka Putih"), jadi lebih aman digambar manual.
- Menghitung `outlet_count` per area (diisi 0 seperti Design Area).
- Batas tingkat kota/kecamatan dari dataset: jauh lebih kasar (Kec. Gambir hanya 4 titik), jadi tidak dipakai.

## Testing

`tests/Feature/GenerateKelurahanTest.php` (HTTP ke GitHub di-fake):

- Unduh sekali per kota.
- MultiPolygon tetap utuh dan koordinat dibalik ke `[lng, lat]`.
- Kelurahan tanpa kode atau tanpa batas.
- Peringatan saat 404.
- Simpan membuat 1 area per kelurahan dengan kode urut dan cincin terluas.
- Simpan ulang dilewati; area lama dikenali lewat nama.
- Hak `tambah`.
- Command impor dari folder lokal.
