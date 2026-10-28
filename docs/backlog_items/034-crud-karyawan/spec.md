# CRUD Karyawan (Sales, Koordinator, Supervisor, Lainnya)

## Problem Statement
Menu Karyawan Sales, Karyawan Koordinator, Karyawan Supervisor, dan Karyawan Lainnya hanya menampilkan salinan daftar Marketama. Isinya tidak bisa diubah, padahal `mk_karyawan` dipakai aplikasi HP (rute dari area sales, depo, koordinator), akun login, cakupan role, dan semua laporan motoris. Di Marketama keempat menu punya Tambah / Edit / Hapus / Export, dan menu sales juga punya Edit Area Aktif serta Pindah Area ke Sales Lain.

## Form Marketama (28 Okt 2026)
- **Sales** (`/karyawan_sales/add`): nama*, depo*, koordinator*, area*, email, no HP*, tanggal mulai bekerja*, status*, gaji pokok*, tunjangan BBM & makan, sewa motor, tunjangan kuota internet, foto. Aksi tambahan: Edit Area Aktif (pilih area aktif dari area sales), Pindah Area ke Sales Lain (mode ganti / gabung). 1.384 sales.
- **Koordinator** (`/karyawan/add`): sama tanpa koordinator & area, dengan Wilayah Depo. 83 koordinator.
- **Supervisor** (`/karyawan_supervisor/add`): tanpa depo. 3 baris.
- **Lainnya** (`/karyawan_lainnya/add`): + Kategori Karyawan (Supervisor, Principal, Auditor); no HP, tanggal mulai, dan gaji opsional. 6 baris.

## Solution
- Migration `2026_10_28_000004`: `mk_karyawan` + `jabatan`, `wilayah_depo_id`, `gaji_pokok`, `tunjangan_bbm_makan`, `sewa_motor`, `tunjangan_internet`, `foto`, `diubah_oleh`, `diubah_pada`. Jabatan Karyawan Lainnya dan wilayah depo koordinator diisi dari kolom `data` hasil tarikan.
- `KaryawanController` (route per menu, hak lihat/tambah/ubah/hapus/ekspor):
  - daftar dengan saring status (semua/aktif/tidak aktif), depo, dan cari nama/HP/email/koordinator;
  - tambah/edit di modal kanan (JSON) atau halaman `/{menu}/{id}/edit`;
  - hapus, ekspor CSV, foto.
- Sales:
  - koordinator harus dari depo yang sama; disimpan lewat `HierarkiSales::atur`;
  - area (multi, area depo itu) dan area aktif (bagian dari area) disimpan di `mk_karyawan_area`; `area_aktif_id` = area aktif pertama;
  - Pindah Area ke sales lain di depo yang sama, mode gabung atau ganti.
- Pilihan per depo (koordinator, area, wilayah depo) dari `GET /{menu}/opsi?depo=`.
- ID karyawan baru mulai 100.000.000 (tidak bentrok dengan ID Marketama).
- Hapus ditolak bila karyawan punya akun login, sales di bawahnya, atau transaksi (absensi, kunjungan, penjualan, canvassing, join visit, outlet baru). Pesan menyarankan status Tidak aktif.
- Cakupan role berlaku (`Akses::batasiKaryawan`); karyawan di luar cakupan / kategori lain = 404.
- Setiap perubahan dicatat per kolom di `riwayat_admin` (objek `karyawan`).
- `marketama:tarik` tidak lagi menimpa karyawan yang sudah diubah di Enesis (`diubah_pada` terisi).
- `FormSkeleton` dipindah ke `Components/ui/molecules` (dipakai master produk dan karyawan).
- Keempat menu masuk `menu_motoris` (tanpa bintang).

## Belum termasuk
- Menonaktifkan akun login otomatis saat karyawan dinonaktifkan.
- Lap. Karyawan.
- Sales Tidak Aktif (menu Marketama terpisah; di Enesis cukup saring status).

## Testing
`tests/Feature/Admin/KaryawanTest.php`:
- daftar per kategori + saring status / depo / cari;
- tambah sales (validasi area & koordinator depo, gaji, foto, hierarki, area aktif);
- ubah + riwayat;
- koordinator wilayah depo & jabatan Karyawan Lainnya;
- hapus ditolak & pindah area;
- hak akses & cakupan depo;
- ekspor CSV.
