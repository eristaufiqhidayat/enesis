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

## Tarik data langsung dari aplikasi lama (`marketama:tarik`)

Mengisi tabel baru `mk_*` langsung dari app.cnvmarketama.com memakai akun admin, dengan
**ID asli Marketama** sebagai kunci (bukan teks nama), sehingga relasi antar tabel pasti.
Perintah ini **hanya membaca**: klien hanya boleh GET, POST login, dan POST ke endpoint
DataTables; alamat simpan/ubah/hapus ditolak oleh kode (`MarketamaClient::postDiizinkan`).

```bash
# .env server: MARKETAMA_USERNAME / MARKETAMA_PASSWORD (akun admin), MARKETAMA_JEDA_MS=300
php artisan migrate
php artisan marketama:tarik master            # depo, wilayah depo, area, distributor, produk, koordinator
php artisan marketama:tarik karyawan          # semua sales (aktif + tidak aktif), supervisor, lainnya + form edit
php artisan marketama:tarik user              # user sales/koordinator/lainnya/super admin + id karyawan
php artisan marketama:tarik outlet            # ±552 ribu outlet (±2 jam)
php artisan marketama:tarik mentah:penjualan_barang   # menu lain apa adanya ke mk_baris_mentah
php artisan marketama:tarik sambung           # isi area_id outlet / karyawan_id user dari data tersimpan
php artisan marketama:tarik --status          # progres per menu vs total di sumber
```

| Tabel | Isi | Sumber ID |
| --- | --- | --- |
| `mk_depo`, `mk_wilayah_depo`, `mk_area`, `mk_distributor`, `mk_produk` | master | `get_depo`, `data-id` baris, dropdown form edit |
| `mk_karyawan` | sales (aktif + tidak aktif), koordinator, supervisor, lainnya; `depo_id`, `koordinator_id` | form edit karyawan (`warehouse_id`, `koordinator_id`) |
| `mk_karyawan_area` | area tiap sales + area aktif | `data-areas` di form edit |
| `mk_user` | akun login (tanpa password) + `karyawan_id` | `id_employee` di form edit user |
| `mk_outlet` | outlet + `area_id` | `data-id` baris; area dari nama area (unik) |
| `mk_baris_mentah` | baris menu lain (transaksi) sebagai JSON | `data-id` baris |
| `mk_tarikan` | posisi & jumlah per menu (untuk melanjutkan) | — |

- Bisa dihentikan kapan saja; jalankan perintah yang sama untuk melanjutkan. `--ulang` menarik dari awal,
  `--batas=50` untuk uji coba, `--tanpa-detail` melewati form edit (lebih cepat, relasi dari nama).
- Gaji/tunjangan di form edit karyawan **tidak** disimpan. Data `mk_*` berisi data pribadi
  (nama, HP, email): jangan diekspor ke repo.
- `php artisan db:seed --class=MarketamaSumberSeeder` menjalankan semua bagian sekaligus.

## Akun login dari data lama (`enesis:sinkron-user`)

Mengisi tabel `users` dari `mk_user` + `mk_karyawan` (hasil `marketama:tarik`). Login memakai
**username**; password lama tidak bisa diambil, jadi setiap akun diberi password acak + wajib ganti.

```bash
php artisan migrate
php artisan enesis:sinkron-user              # buat/perbarui akun + daftar kasus perlu keputusan admin
php artisan enesis:sinkron-user --daftar     # tampilkan ulang daftar perlu keputusan
php artisan enesis:atur-password <username>  # password awal acak (ditampilkan sekali)
```

Aturan: akun aktif milik karyawan nonaktif → nonaktif; username kembar (tanpa beda huruf besar/kecil)
→ akun aktif memakai username, lainnya diberi akhiran `_<id>`; karyawan dengan >1 akun aktif atau
dua akun aktif berusername sama → `perlu_keputusan = true` (tetap aktif sampai admin memilih).
Aman dijalankan ulang; password yang sudah diatur tidak ditimpa. Halaman login: backlog 001.

## Sambungkan transaksi ke master (`enesis:sambung-transaksi`)

Mengisi kolom `karyawan_id`, `koordinator_id`, `depo_id`, `area_id`, `outlet_id`, `produk_id`,
`distributor_id` di tabel transaksi `lap_*` (penjualan, effective call, checkin outlet, stok outlet,
join visit, absensi sales, canvassing, pengembalian, loading) dari master `mk_*`. Teks nama asli
tidak diubah. Nama sales yang kembar diselesaikan dengan depo transaksi. Jalankan ulang setelah
`marketama:import` (impor CSV mengosongkan kolom ini).

```bash
php artisan enesis:sambung-transaksi                         # semua tabel (±5–10 menit)
php artisan enesis:sambung-transaksi --tabel=lap_penjualan_barang
```

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
