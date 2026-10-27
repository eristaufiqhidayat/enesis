# Ubah & Hapus Polygon Area

## Problem Statement

Design Area hanya bisa menggambar area baru dan menghapus area. Area yang sudah ada (gambar manual, hasil Generate Polygon Kelurahan, Auto Grid, atau tarikan Marketama) tidak bisa diubah nama, depo, keterangan, maupun bentuknya. Di Generate Polygon Kelurahan, kelurahan berstatus "Sudah punya area" juga tidak bisa dibuka atau dihapus areanya, jadi area yang salah tidak bisa dibuat ulang.

## Solution

### Design Area (`master_area/design_area`)
- Popup area punya tombol **Ubah Data**, **Ubah Bentuk** (hak `ubah`), dan **Hapus** (hak `hapus`).
- **Ubah Data**: modal yang sama dengan simpan area baru (nama, depo, keterangan).
- **Ubah Bentuk**: editor tanpa plugin (`Pages/Area/partials/editorPolygon.js`).
  - Seret titik sudut untuk memindah, klik titik untuk menghapus (minimal 3 titik per cincin), seret/klik titik tengah untuk menyisipkan titik.
  - Semua pulau dan lubang (`geometri` MultiPolygon) ikut bisa diedit; area lama satu cincin diedit dari kolom `polygon`.
  - **Urungkan** sampai 50 langkah, **Batal**, **Simpan Bentuk**.
  - Penanda hanya digambar untuk titik di layar; bila lebih dari 600, user diminta memperbesar peta (polygon BIG bisa ribuan titik).
- Konfirmasi hapus menyebut kode area, jumlah toko, dan sales bila ada.
- `?area=<id>` membuka Design Area langsung pada area itu (filter wilayah terisi, peta fokus).

### API
- `PUT master_area/design_area/areas/{id}` (hak `ubah`): `name`, `warehouse_id`, `info`, opsional `geometri` (koordinat MultiPolygon `[lng, lat]`).
  - Geometri divalidasi: tiap cincin minimal 3 titik, koordinat dalam rentang, maksimal 50.000 titik; cincin ditutup dan dibulatkan 6 desimal.
  - `geometri` disimpan utuh, `polygon` diisi cincin terluas, `sumber_polygon = diubah`.
  - Tanpa `geometri`, bentuk tidak berubah.
- `DELETE …/areas/{id}` sekarang 404 bila area tidak ada.

### Generate Polygon Kelurahan
- Klik kelurahan "Sudah punya area" membuka popup daftar areanya dengan tombol **Ubah** (ke Design Area `?area=`) dan **Hapus**. Setelah dihapus, daftar dimuat ulang dan kelurahan kembali "Siap dibuat".
- Teks halaman menyebut sumber batas yang dipakai (peta BIG, cadangan dataset Kemendagri).

## Di luar cakupan
- Menambah/menghapus pulau atau lubang utuh, memotong/menggabung area.
- Cek tumpang tindih dengan area lain saat menyimpan bentuk.
- Riwayat perubahan per area.

## Testing
`tests/Feature/DesignAreaTest.php`: ubah data tanpa mengubah bentuk; ubah bentuk (pulau + lubang, cincin terbuka ditutup, cincin terluas ke `polygon`); bentuk tidak valid & area tidak ada ditolak; hak `ubah`/`hapus`; `?area=` mengisi prop `fokus`. `GenerateKelurahanTest` memeriksa prop hak ubah/hapus.
