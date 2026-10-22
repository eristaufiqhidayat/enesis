# Enesis Canvassing

Aplikasi Laravel 13 dengan menu sidebar yang sama dengan **Marketama Canvassing System**
(9 grup, 119 menu, ikon dan urutan sama). Setiap menu menampilkan tabel data hasil
penarikan dari Marketama, lengkap dengan pencarian dan paginasi.

## Struktur

| Bagian | Lokasi |
| --- | --- |
| Struktur menu, daftar halaman, label kolom | `config/marketama.php` |
| Sidebar & layout | `resources/views/layouts/` |
| Halaman menu (tabel + cari) | `app/Http/Controllers/MenuController.php`, `resources/views/menu/show.blade.php` |
| Migration semua tabel (101 tabel) | `database/migrations/2026_10_22_000001_create_marketama_tables.php` |
| Seeder data referensi | `database/seeders/ReferenceDataSeeder.php`, CSV di `database/seeders/data/` |
| Perintah impor data lengkap | `app/Console/Commands/ImportMarketama.php` |

Beberapa menu Marketama menampilkan data yang sama dengan menu laporannya
(misalnya *Penjualan Barang* = *Lap. Penjualan Barang*). Menu seperti itu memakai tabel
laporannya dan menampilkan tautan ke sana.

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite      # atau atur DB MySQL di .env
php artisan migrate --seed
php artisan serve
```

`--seed` mengisi **data referensi tanpa data pribadi**: provinsi, kota/kabupaten,
kecamatan, kelurahan, city group, produk, brand, kategori, satuan, depo, wilayah depo,
project, tanggal libur, tipe/kategori outlet, kategori user/karyawan, hari kerja aktif,
target, parameter potongan gaji, dan rumus insentif.

## Impor data lengkap

Data transaksi, karyawan, user, dan outlet **tidak disimpan di repo** karena berisi data
pribadi dan ukurannya besar (sampai 1,7 juta baris per tabel). Data ini dimuat dari
folder CSV hasil tarikan (satu file per tabel, nama file = nama tabel):

```bash
php artisan marketama:import /path/ke/folder-csv
php artisan marketama:import /path/ke/folder-csv --only=lap_penjualan_barang --only=user_sales
```

Setiap tabel dikosongkan dulu sebelum diisi ulang.

Rentang data hasil tarikan 22 Okt 2026: data transaksi 1–22 Okt 2026, master data lengkap.
