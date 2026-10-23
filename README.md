# Enesis Canvassing

Aplikasi Laravel 13 dengan menu sidebar yang sama dengan **Marketama Canvassing System**
(9 grup, 119 menu, ikon dan urutan sama). Setiap menu menampilkan tabel data hasil
penarikan dari Marketama, lengkap dengan pencarian dan paginasi.

## Struktur

| Bagian | Lokasi |
| --- | --- |
| Struktur menu, daftar halaman, label kolom | `config/marketama.php` |
| Layout Blade (shell halaman non-Inertia) | `resources/views/layouts/app.blade.php` |
| Layout Inertia/Vue | `resources/js/Layouts/AppLayout.vue` |
| Komponen UI reusable (atomic: atoms/molecules/organisms) | `resources/views/components/ui/` (Blade), `resources/js/Components/ui/` (Vue) |
| Halaman menu (tabel + cari) | `app/Http/Controllers/MenuController.php`, `resources/js/Pages/Menu/Show.vue` |
| Migration semua tabel (101 tabel) | `database/migrations/2026_10_22_000001_create_marketama_tables.php` |
| Seeder data referensi | `database/seeders/ReferenceDataSeeder.php`, CSV di `database/seeders/data/` |
| Perintah impor data lengkap | `app/Console/Commands/ImportMarketama.php` |

### Komponen UI

Aplikasi ini **wajib** memakai komponen UI reusable, bukan HTML yang ditulis ulang di
setiap halaman. Ada dua pustaka paralel karena ada dua cara render (halaman menu memakai
Inertia + Vue, halaman custom seperti tracking/area memakai Blade biasa), tapi keduanya
disusun dengan pola atomic design yang sama dan memakai class komponen yang sama dari
`resources/css/app.css` (`.card`, `.btn`, `.badge`, `.form-control`, `.data-table`, dst.):

```
resources/views/components/ui/      resources/js/Components/ui/
├── atoms/                          ├── atoms/
│   ├── button.blade.php            │   ├── Button.vue
│   ├── badge.blade.php             │   ├── Badge.vue
│   ├── icon.blade.php              │   └── Icon.vue
│   ├── label.blade.php             ├── molecules/
│   ├── input.blade.php             │   ├── Card.vue
│   ├── select.blade.php            │   ├── Alert.vue
│   └── textarea.blade.php          │   └── Pagination.vue
├── molecules/                      └── organisms/
│   ├── card.blade.php                  ├── Sidebar.vue
│   ├── alert.blade.php                 ├── Topbar.vue
│   └── modal.blade.php                 └── DataTable.vue
└── organisms/
    ├── sidebar.blade.php
    └── topbar.blade.php
```

Contoh pakai di Blade:

```blade
<x-ui.molecules.card body-class="card-body text-gray-500">
    <x-slot:header>Judul Kartu</x-slot:header>
    Isi kartu...
</x-ui.molecules.card>
```

Contoh pakai di Vue:

```vue
<DataTable :title="table.title" :columns="table.columns" :rows="table.rows.data"
           :total="table.rows.total" :links="table.rows.links" />
```

Komponen baru ditambah ke folder yang sesuai begitu sebuah potongan markup dipakai di
≥2 tempat. Lihat `CLAUDE.md` untuk aturan lengkapnya.

Beberapa menu Marketama menampilkan data yang sama dengan menu laporannya
(misalnya *Penjualan Barang* = *Lap. Penjualan Barang*). Menu seperti itu memakai tabel
laporannya dan menampilkan tautan ke sana.

## Instalasi

```bash
composer install
cp .env.example .env
php artisan key:generate
# buat database MySQL `enesis` (utf8mb4), lalu sesuaikan DB_* di .env
mkdir -p storage/app/marketama
unzip marketama_csv_import_1-22okt2026.zip -d storage/app/marketama
php artisan migrate --seed
php artisan serve
```

Default `.env.example` memakai MySQL (`DB_CONNECTION=mysql`, database `enesis`).

### PostgreSQL

Migration dan seeder yang sama juga jalan di PostgreSQL (diuji di PostgreSQL 16). Buat
database `enesis` (encoding UTF8), lalu di `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=enesis
DB_USERNAME=postgres
DB_PASSWORD=rahasia
```

Lalu jalankan `php artisan migrate --seed` seperti biasa. Butuh ekstensi PHP `pdo_pgsql`.

`--seed` menjalankan dua seeder:

1. `ReferenceDataSeeder`: **data referensi tanpa data pribadi** dari `database/seeders/data/`
   (provinsi, kota/kabupaten, kecamatan, kelurahan, city group, produk, brand, kategori, satuan,
   depo, wilayah depo, project, tanggal libur, tipe/kategori outlet, kategori user/karyawan,
   hari kerja aktif, target, parameter potongan gaji, rumus insentif).
2. `MarketamaDataSeeder`: **semua data hasil tarikan lainnya** (transaksi 1–22 Okt 2026,
   karyawan, user, outlet, tracking, polygon area) dari folder `storage/app/marketama`
   (atau `MARKETAMA_DATA_PATH` di `.env`). File boleh `.csv` atau `.csv.gz`.

Data transaksi, karyawan, user, dan outlet **tidak disimpan di repo** karena berisi data
pribadi dan ukurannya besar (sampai 1,7 juta baris per tabel). Folder `storage/app` sudah
di-`.gitignore`. Kalau folder kosong, seeder hanya memberi peringatan.

Seeder data besar bisa dijalankan ulang sendiri:

```bash
php artisan db:seed --class=MarketamaDataSeeder
```

## Impor data dari folder lain

Data juga bisa dimuat dari folder CSV mana pun (satu file per tabel, nama file = nama tabel):

```bash
php artisan marketama:import /path/ke/folder-csv
php artisan marketama:import /path/ke/folder-csv --only=lap_penjualan_barang --only=user_sales
```

Setiap tabel dikosongkan dulu sebelum diisi ulang.

## Peta Tracking History Koordinator Location

Halaman `/tracking_koordinator_history_location` menampilkan rute GPS koordinator di peta
Leaflet (pilih tanggal dan koordinator), sama seperti di Marketama: garis rute, ikon Start/End,
titik-titik lokasi dengan alamat, lokasi depo, dan tombol Satelit / 2D Map / Garis Jalan.
Datanya ada di tabel `tracking_koordinator` dan `tracking_koordinator_lokasi`, dimuat lewat
`marketama:import` (file `tracking_koordinator.csv` dan `tracking_koordinator_lokasi.csv`).
Peta memakai tile OpenStreetMap dan Esri, jadi butuh koneksi internet.

## Peta Tracking History Sales Location

Halaman `/tracking_sales_history_location` sama seperti peta koordinator, untuk sales: filter
koordinator, daftar sales dengan jumlah titik GPS per tanggal, rute GPS dan lokasi depo.
Datanya di tabel `tracking_sales` dan `tracking_sales_lokasi` (835 ribu titik, 1–22 Okt 2026),
dimuat lewat seeder atau `marketama:import` (file `tracking_sales.csv` dan
`tracking_sales_lokasi.csv.gz`).

## Menu kanan atas & Design Polygon Area

Ikon kanan atas sama dengan header Marketama: Tambah Loading Barang, Tambah Barang Canvassing,
Design Polygon Area, dan Notifikasi. Halaman `/master_area/design_area` menampilkan polygon
semua area (13.254 area) per provinsi/kota/kecamatan/kelurahan, lokasi depo, daftar area yang
bisa dicari, serta fitur gambar polygon baru (simpan ke database lokal) dan hapus area.
Datanya di tabel `area_polygon` dan `area_depo`, dimuat lewat `marketama:import`
(file `area_polygon.csv` dan `area_depo.csv`). Form Tambah Loading Barang dan Tambah Barang
Canvassing belum dibuat.

Rentang data hasil tarikan 22 Okt 2026: data transaksi 1–22 Okt 2026, master data lengkap.
