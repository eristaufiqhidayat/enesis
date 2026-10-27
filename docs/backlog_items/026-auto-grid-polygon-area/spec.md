# Auto Grid Polygon Area

## Problem Statement

Halaman Marketama `master_area/auto_design_area` membagi satu kota/kecamatan menjadi kotak 1–15 km lalu menyimpan tiap kotak sebagai area depo. Analisa lengkapnya ada di project doc `claude/menu-master-area-auto-grid.md`. Masalah utamanya:

1. **Batas wilayah dari hasil pertama Nominatim.** Di uji coba user (Kab. Banyuasin, 4 km), sebagian kotak berlabel Ogan Komering Ilir.
2. **Kecamatan/kelurahan tiap kotak ditebak lewat reverse geocode Nominatim**, satu request per kotak langsung dari browser (±7 req/detik). 940 kotak butuh beberapa menit, melanggar kebijakan OSM, dan namanya tidak sama dengan master. Hanya titik tengah yang dipakai, padahal satu kotak bisa mencakup beberapa kelurahan.
3. **Kotak di tepi hanya menyimpan pecahan terluas.** Label baris berulang setelah huruf Z.
4. **Hapus Perairan / Perbaiki Polygon memakai API Google Maps** (`setMap`, `setOptions`) di peta Leaflet, jadi error.
5. **Tidak ada cek tumpang tindih** dengan area yang sudah ada.

## Solution

Halaman baru di URL yang sama, `master_area/auto_design_area` (`AutoGridController` + `Area/AutoGrid.vue`). Grid dibangun **di server** dari batas kelurahan resmi di `batas_wilayah`, sama dengan Generate Polygon Kelurahan (025).

- **`App\Support\Area\GridArea`**:
  - Grid berawal dari pojok barat laut wilayah; ukuran kotak 1–15 km (lebar bujur dikoreksi cos lintang).
  - Tiap kelurahan dipotong dengan kotak yang dilewatinya (`Geometri::potongPersegi`, Sutherland–Hodgman per cincin, lubang ikut).
  - Laut dan wilayah luar kota otomatis tidak ikut, karena tidak ada di batas kelurahan.
  - **Dua mode** (dipilih user):
    - **Potong per kelurahan**: 1 area = bagian 1 kelurahan dalam 1 kotak. Kelurahan/kecamatan pasti.
    - **Kotak utuh**: 1 area = 1 kotak (MultiPolygon bagian-bagian kelurahan di dalamnya). Kelurahan utama = bagian terluas, dan keterangan mencatat 3 kelurahan terluas beserta persentasenya.
  - **Pecahan kecil** (< 25% kotak; mode kelurahan: < 25% dari yang lebih kecil antara kotak dan luas kelurahannya) digabung ke tetangga atas/bawah/kiri/kanan yang masih satu kotak, sehingga jadi persegi panjang 1×2 lalu dipotong ulang.
  - Label baris A..Z, AA, AB… + nomor kolom (mis. `A25`, gabungan `A25+A26`). Nama area = "Kelurahan (A25)".
  - Batas 8.000 kotak per grid; lebih dari itu ditolak dengan saran memperbesar ukuran atau memilih kecamatan.
- **Kunci deterministik**: browser hanya mengirim parameter grid + kunci kotak terpilih. Server membangun ulang grid dan menyimpan geometrinya sendiri. Kunci yang tidak ada (batas berubah) ditolak.
- **Tumpang tindih**: kotak yang titik dalamnya jatuh di area yang sudah ada (`AreaTerpasang`, membaca `geometri` atau `polygon` lama) ditandai, tidak dipilih, dan dilewati saat simpan. Grid yang disimpan dua kali tidak dobel.
- **Kolom yang diisi**:
  - `geometri` (MultiPolygon utuh) dan `polygon` (cincin terluas).
  - `branch_area`/`branch_area_id` dari Wilayah Depo opsional (`mk_wilayah_depo`).
  - Wilayah + ID dari kelurahan utama.
  - `sumber_polygon = grid-{n}km-{mode}`.
- **Kode bersama**:
  - Form Depo → Provinsi → Kota → Kecamatan diekstrak ke `Pages/Area/partials/FormWilayah.vue` + `formWilayah.js`, dipakai juga Generate Polygon Kelurahan.
  - Helper server ada di `App\Support\Area\WilayahKota`; `multiKeLatLng` di `Support/peta.js`.
- **Peta**: kotak digambar dengan renderer canvas supaya ribuan kotak tetap ringan. Daftar menampilkan 300 baris pertama (sisanya tetap di peta dan bisa dicari).
- **Navigasi**: tombol Auto Grid di Design Area, dan tautan silang dengan Generate Polygon Kelurahan.

Uji pada data asli (SQLite lokal):

| Wilayah | Ukuran & mode | Area | Waktu |
| --- | --- | --- | --- |
| Kab. Bogor | 4 km kotak | 205 | 0,6 s |
| Kab. Bogor | 4 km per kelurahan | 970 | 0,4 s |
| Kab. Bogor | 1 km kotak | 3.104 (±500 KB gzip) | 0,9 s |

Total luas grid sama dengan total luas kelurahan.

## Di luar cakupan

- Penghapus (eraser) dan "Perbaiki Polygon" (bagi ulang dengan Voronoi). Di versi ini kotak yang tidak diinginkan cukup tidak dipilih. Perairan tidak lagi muncul karena grid dipotong batas kelurahan.
- Menghitung `outlet_count` per area.

## Testing

- `tests/Unit/GridAreaTest.php`:
  - Potong polygon cekung + lubang dengan luas tepat; titik dalam.
  - Kedua mode menjaga total luas dan mencatat kelurahan.
  - Pulau kecil digabung.
  - Label lewat Z; grid terlalu rapat ditolak.
- `tests/Feature/AutoGridTest.php`:
  - Halaman + wilayah depo.
  - Pratinjau dua mode + validasi.
  - Simpan membangun ulang di server, mengisi kolom, lalu kedua kalinya dilewati karena tumpang tindih.
  - Kunci basi ditolak.
  - Hak `tambah`.
