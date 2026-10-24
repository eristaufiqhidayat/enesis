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

## Login

Semua halaman wajib login (`/login`, username + password; logout dari tombol **Keluar** di topbar).
Akun dengan password awal dari admin diarahkan ke `/ganti-password` sampai password diganti.
Lima kali gagal login dari username + IP yang sama dikunci 60 detik.

**Demo masuk cepat**: bila `DEMO_LOGIN=true` (bawaan hanya di `APP_ENV=local`), form login
menampilkan tombol Super Admin / Koordinator / Sales / Lainnya yang masuk tanpa password sebagai
akun demo `demo.<peran>` (dibuat otomatis, bukan akun karyawan). Jangan aktifkan di server berisi data asli.

- Lokal/dev: `php artisan migrate --seed` membuat akun `admin` / `password` (`UserSeeder`).
- Production: akun dari data lama lewat `enesis:sinkron-user` (di bawah), password awal lewat
  `enesis:atur-password <username>`. `UserSeeder` hanya membuat `admin` bila `SEED_ADMIN_PASSWORD` diisi.

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
php artisan enesis:sambung-transaksi --kolom=karyawan_id --kolom=koordinator_id   # hanya kolom tertentu
```

## Migrasi data lama ke tabel motoris (`trx_*`)

Riwayat aplikasi lama disalin ke tabel yang dipakai aplikasi motoris, supaya data lama dan data
motoris ada di satu tabel. Baris hasil migrasi bertanda `sumber = 'lama'` dan `sumber_id` = `source_id`
aslinya; baris dari HP bertanda `sumber = 'motoris'`. Tabel `lap_*` tidak diubah (tetap arsip).

| Jenis | Dari | Ke | Catatan |
| --- | --- | --- | --- |
| `absensi` | `lap_absensi_sales` | `trx_absensi` | jam masuk/pulang; terlambat dihitung dari batas jam masuk; tanpa GPS & selfie |
| `checkin_depo` | `lap_checkin_checkout` | `trx_checkin_depo` | jam checkin/checkout, GPS; depo kosong memakai depo karyawan |
| `kunjungan` | `lap_checkin_outlet` | `trx_kunjungan` | outlet dari kode outlet; jarak sales–outlet & durasi dihitung; foto lama disimpan sebagai link (`foto_url`); `category`/`description` disimpan apa adanya (`kategori_lama`, `keterangan_lama`) |

```bash
php artisan enesis:migrasi-transaksi                 # semua jenis
php artisan enesis:migrasi-transaksi absensi         # satu jenis
php artisan enesis:migrasi-transaksi --tanpa-sambung # lewati pengisian ID karyawan/depo di tabel lama
```

Aman diulang: hanya baris lama yang belum pernah dimigrasi yang ditambahkan. Absensi & checkin depo: satu sales satu baris
per tanggal; bila tanggal itu sudah ada (mis. sales sudah absen lewat motoris), baris lama dilewati dan
data motoris tidak ditimpa. Waktu di data lama dibaca sebagai jam lokal (`MOBILE_ZONA_WAKTU`) lalu
disimpan dalam UTC, sama seperti data dari HP.

Menu **Absensi Sales**, **Absensi Sales Terlambat**, **Lap. Absensi Sales**, **Checkin Checkout Depo**,
**Lap. Checkin Checkout**, **Checkin Outlet**, dan **Lap. Checkin Outlet** membaca `trx_absensi` /
`trx_checkin_depo` / `trx_kunjungan` (`KehadiranController`): filter rentang
tanggal (bawaan 7 hari terakhir, maks. 92 hari), depo, koordinator, nama sales, dan sumber data; jam
ditampilkan dalam WIB; selfie absen dibuka lewat `/foto/{id}` (hanya untuk yang boleh melihat menu absensi
dan sales itu ada dalam cakupan datanya).

## Kelola akun & role (`/user_sales`, `/user_koordinator`, `/user_lainnya`, `/super_admin`, `/role`)

Empat menu Users mengelola akun login Enesis (tabel `users`): daftar dengan filter status (aktif, tidak
aktif, perlu keputusan, belum ganti password), cari, filter depo, HP yang sedang login; tambah/edit akun
(kategori, karyawan, role, aktif); detail akun dengan sesi HP dan riwayat; password awal acak yang tampil
sekali; aktif/nonaktif (nonaktif mencabut sesi HP); cabut sesi HP; keputusan akun ganda; hapus hanya untuk
akun buatan Enesis yang belum pernah login.

Menu **Role & Hak Akses** (`/role`): setiap akun punya satu role yang menentukan menu yang terlihat, aksi per
menu (lihat/tambah/ubah/hapus/ekspor), cakupan data (semua / depo sendiri / per downline / diri sendiri), dan
boleh login di web dan/atau aplikasi HP. Migrasi membuat 4 role bawaan sesuai kategori lama dan mengisi
`users.role_id`:

| Role bawaan | Cakupan | Web | HP | Hak awal |
|---|---|---|---|---|
| Super Admin | Semua | ✓ | – | semua menu |
| Koordinator | Per downline | ✓ | ✓ | lihat + ekspor semua menu kecuali kelola akun/role |
| Sales Motoris | Diri sendiri | – | ✓ | Beranda |
| Lainnya | Depo sendiri | ✓ | – | lihat + ekspor semua menu kecuali kelola akun/role |

Hak dicek di setiap rute (middleware `hak:<menu>,<aksi>`) dan sidebar hanya menampilkan menu yang boleh
dilihat. Cakupan data saat ini diterapkan di menu kelola akun; menu laporan lain menyusul. Perubahan akun &
role dicatat di `riwayat_admin`. Akun tanpa kategori dan tanpa role (akun teknis) tidak dibatasi.

## API aplikasi mobile (`/api/mobile/v1`)

Tahap 1 untuk **sales motoris**, mengikuti mockup aplikasi (Beranda, Absen, Terima barang, Rute,
Kunjungan, Order, Outlet baru, Tutup hari). Kontrak lengkap: [`docs/api/mobile-v1.yaml`](docs/api/mobile-v1.yaml)
(OpenAPI 3.1; buka di editor.swagger.io).

- Login `POST /auth/login` (username + password + `device_id`) → token Bearer per HP. Satu akun satu HP:
  login di HP lain mencabut token lama (`401 device_replaced`); token menganggur 30 hari dicabut.
  Akun dengan password awal harus `POST /auth/change-password` dulu.
- Transaksi disimpan di tabel baru `trx_*` (`trx_absensi`, `trx_checkin_depo`, `trx_canvassing(_item)`,
  `trx_kunjungan`, `trx_stok_outlet`, `trx_penjualan(_item)`, `trx_outlet_baru`, `trx_tracking`) dengan ID
  master `mk_*`. Foto di `mobile_uploads` (disk `local`, folder `mobile/<user>/<tahun>/<bulan>`).
- POST transaksi wajib header `Idempotency-Key` (UUID) sehingga antrian offline aman dikirim ulang.
- Aturan bisnis (radius depo/outlet/duplikat, jam aktif, batas masuk, minimal transaksi, poin) ada di
  `config/mobile.php` dan bisa diubah lewat `.env` (`MOBILE_*`). Jam aktif dan libur dibaca dari
  `waktu_aktif_app` dan `holiday_dates`; koordinat, minimal transaksi, dan target dari data depo.

```bash
php artisan migrate
# Muatan canvassing untuk uji (form web staf depo belum ada):
php artisan enesis:siapkan-canvassing <karyawan_id> --produk=<produk_id>:<qty> --produk=...
php artisan enesis:bersihkan-mobile   # dijadwalkan tiap 02:00 (perlu cron `schedule:run`)
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
