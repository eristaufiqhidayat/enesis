# Data Outlet Baru selengkap Marketama (New Open Outlet)

## Problem Statement
Menu New Open Outlet di Marketama (`/outlet_noo`) menampilkan outlet yang dibuat pada hari itu, lengkap dengan:
- contact person 1/2 dan no HP 1/2;
- kategori outlet dan gambar toko;
- provinsi, kota/kabupaten, kecamatan, kelurahan;
- status fiktif dan verifikasi.

Di Enesis, NOO adalah antrean pendaftaran outlet dari HP (`trx_outlet_baru`), dan keputusan product owner (28 Okt 2026) adalah **tetap menunggu persetujuan**. Tetapi datanya belum lengkap:
- contact person dan no HP kedua belum ada;
- wilayah kosong;
- kontak, no HP, tipe, dan foto tidak ikut masuk `mk_outlet` saat disetujui;
- menu New Open Outlet menampilkan semua tanggal.

## Solution
- **Kolom baru** (migration `2026_10_28_000002`):
  - `trx_outlet_baru`: `pemilik2`, `no_hp2`, `provinsi`, `kota_kabupaten`, `kecamatan`, `kelurahan`, `kode_kelurahan`, `tanggal` (tanggal daftar WIB, diisi untuk data lama).
  - `mk_outlet`: `kontak1/2`, `no_hp1/2`, `tipe`, `foto_id`, `karyawan_id`, `kode_kelurahan`. Outlet yang sudah disetujui ikut diisi dari pendaftarannya.
- **Wilayah dari titik GPS** (`App\Support\Outlet\WilayahOutlet`):
  - Utama: kelurahan resmi yang memuat titik, dari `batas_wilayah` (`Geometri::dalam`); nama induk diambil dari master `kelurahan` berkode sama.
  - Cadangan: wilayah area outlet (`mk_area.data`).
  - Dihitung saat outlet didaftarkan dari HP dan saat disetujui (untuk pendaftaran lama).
  - Data lama: `php artisan enesis:wilayah-outlet` (`--ulang` untuk hitung ulang). Hasil terbaik bila batas kelurahan kota itu sudah dimuat (`wilayah:impor-batas`).
- **API HP** (kontrak v1.2.0):
  - `POST /outlets` menerima `owner_name_2` dan `phone_2` (opsional).
  - `GET /outlets/{id}` mengembalikan `owner_name`, `owner_name_2`, `phone`, `phone_2`, dan `type`.
- **Persetujuan**: kontak 1/2, no HP 1/2, tipe, foto toko, sales pembuat, dan wilayah disalin ke `mk_outlet`; `status_fiktif` = "Tidak".
- **Menu**:
  - New Open Outlet (`outlet_noo`) disaring per tanggal daftar, bawaan hari ini (WIB), seperti Marketama.
  - Lap. Outlet NOO tetap menampilkan semua tanggal.
  - Kolom Contact Person 2, Phone 2, dan wilayah kini terisi; pencarian mencakup kolom-kolom itu.

## Belum termasuk
- Kode outlet tetap `NOO` + 6 digit ID pendaftaran (Marketama `OUT-{id}`).
- Penandaan outlet fiktif di web (menu Outlet Duplikat / Fiktif) belum dibuat.
- Edit kontak outlet dari web (Marketama: nama, contact person 1/2, no HP 1/2) belum dibuat.

## Testing
`tests/Feature/Mobile/OutletBaruLengkapTest.php`:
- daftar outlet: kontak kedua, wilayah dari batas resmi atau cadangan area, tanggal WIB;
- persetujuan menyalin data ke master + detail API;
- menu New Open Outlet per tanggal vs Lap. Outlet NOO semua tanggal;
- perintah `enesis:wilayah-outlet`.

`MenuOutletMotorisTest` memakai Lap. Outlet NOO untuk tampilan semua tanggal.
