# Area Polygon ke mk_area & Menu Master Area dari area_polygon

## Problem Statement

Tiga tabel area tidak saling terhubung:

| Tabel | Isi | Dipakai |
|---|---|---|
| `area_polygon` | peta area (polygon) | Design Area, Generate Polygon Kelurahan, Auto Grid |
| `mk_area` | master area, ID asli Marketama | HP, `mk_outlet.area_id`, `mk_karyawan_area` (rute sales), transaksi |
| `master_area` | salinan datar menu Master Area Marketama (CSV) | hanya tampilan menu Master Area |

`mk_area` diisi `marketama:tarik` dari `area_polygon` yang ber-`source_id`. Area buatan Enesis (`source_id` kosong) tidak pernah masuk `mk_area`, jadi tidak bisa dipakai HP, outlet, atau penugasan sales, dan tidak tampil di menu Master Area.

## Solution

- `App\Support\Area\AreaMk` menyalin `area_polygon` ke `mk_area`:
  - ID mk_area area Marketama = `source_id` (sama dengan `Penarik`); area Enesis = `1.000.000.000 + area_polygon.id` (tidak bertabrakan dengan ID Marketama < 30.000, bisa dihitung tanpa kolom baru);
  - kolom `kode`, `nama`, `depo_id` (= warehouse_id), `data` (kolom area + `sumber` `enesis`/`area_polygon`, `area_polygon_id`).
- Dipanggil di dalam transaksi saat area dibuat/diubah di Design Area, Generate Polygon Kelurahan, dan Auto Grid.
- **Hapus area ditolak (422)** bila ID mk_area-nya masih dipakai `mk_outlet`, `mk_karyawan_area`, `mk_karyawan.area_aktif_id`, `trx_outlet_baru`, atau `trx_join_visit`; pesannya menyebut jumlah per rujukan. Bila bebas, baris `mk_area` ikut dihapus.
- Kolom baru `area_polygon.diubah_oleh` / `diubah_pada`, diisi di semua jalur simpan.
- Menu **Master Area** (`/master_area`) dibaca dari `area_polygon` lewat `SumberMasterArea`:
  - Total Toko = jumlah `mk_outlet` per ID mk_area; Nama Sales dari `mk_karyawan_area` (cadangan kolom `salesperson`);
  - Jarak terdekat/terjauh ke depo dihitung dari titik sudut area ke koordinat `area_depo`;
  - Diupdate Oleh/Pada dari kolom baru. Menu tidak lagi bertanda `*`.
- `php artisan area:sinkron-mk [--semua]` mengisi `mk_area` untuk area Enesis yang sudah ada.

## Di luar cakupan
- Menu Pembagian Area ke Sales (menulis `mk_karyawan_area`) dan Perbaiki Area Outlet (mengisi `mk_outlet.area_id` lewat titik-dalam-polygon). Setelah PR ini, keduanya bisa dibangun di atas ID mk_area area Enesis.
- Menghapus tabel `master_area` (dibiarkan sebagai arsip tarikan).

## Testing
`tests/Feature/AreaMkTest.php`: area baru & ubah tersalin ke mk_area dengan ID offset; area Marketama memakai ID asli; hapus ditolak bila dipakai penugasan sales dan menghapus mk_area bila bebas; menu Master Area menampilkan kode, total toko, nama sales, depo, jarak; command backfill.
