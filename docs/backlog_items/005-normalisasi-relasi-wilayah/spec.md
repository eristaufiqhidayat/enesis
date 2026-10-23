# Normalisasi Relasi Wilayah (Provinsi / Kota Kabupaten / Kecamatan / Kelurahan)

> Backlog item: [005](../../BACKLOG.md)
> GitHub issue: [#16](https://github.com/eristaufiqhidayat/enesis/issues/16)

## Problem Statement

Tabel hierarki wilayah (`kota_kabupaten`, `kecamatan`, `kelurahan`) menyimpan nama induknya sebagai kolom string, bukan foreign key ke tabel induk:

- `kota_kabupaten.provinsi` berisi string nama provinsi (mis. `"Papua Barat Daya"`), padahal tabel `provinsi` sudah ada dengan `kode` + `nama_provinsi`.
- `kecamatan.provinsi` dan `kecamatan.kota_kabupaten` juga string nama, bukan ID.
- `kelurahan.provinsi`, `kelurahan.kota_kabupaten`, `kelurahan.kecamatan` juga string nama.

Akibatnya tidak ada cara ber-JOIN yang benar antar level wilayah: pencarian/pencocokan antar level harus pakai string matching nama (rawan typo, beda kapitalisasi, beda spasi), dan integritas data antar level tidak terjamin oleh skema (bisa saja `kota_kabupaten.provinsi` berisi nama provinsi yang tidak ada di tabel `provinsi`).

Ini terjadi karena ekstraksi awal dari `marketama_csv_import_1-22okt2026.zip` mengambil kolom nama apa adanya dari CSV sumber tanpa resolve ke ID tabel induk yang sudah dimuat lebih dulu.

## Solution

Tambahkan kolom `*_id` (foreign key) pada `kota_kabupaten`, `kecamatan`, dan `kelurahan` yang merujuk ke tabel induknya, isi lewat proses resolve nama → ID saat seeding/import (bukan saat runtime), lalu hapus kolom string nama induk yang lama. Tabel generik (`MenuController`) tetap bisa menampilkan nama induk di UI lewat JOIN yang dideklarasikan di config, bukan lagi kolom mentah.

Scope dibatasi ke 4 tabel hierarki wilayah (`provinsi`, `kota_kabupaten`, `kecamatan`, `kelurahan`) — bukan tabel laporan (`lap_*`) atau `master_depo` yang juga punya kolom `provinsi`/`kota_kab`/dst (lihat Out of Scope).

## User Stories

1. Sebagai developer yang menjalankan `php artisan migrate --seed`, saya ingin tabel `kota_kabupaten` punya `provinsi_id` yang valid merujuk ke `provinsi.id`, supaya saya bisa JOIN langsung tanpa string matching.
2. Sebagai developer, saya ingin tabel `kecamatan` punya `provinsi_id` dan `kota_kabupaten_id`, supaya relasi 3 level (provinsi → kota/kab → kecamatan) konsisten.
3. Sebagai developer, saya ingin tabel `kelurahan` punya `provinsi_id`, `kota_kabupaten_id`, dan `kecamatan_id`, supaya relasi 4 level lengkap dan bisa di-drill-down dengan JOIN.
4. Sebagai admin yang membuka menu "Kota Kabupaten" / "Kecamatan" / "Kelurahan" di sidebar, saya ingin tetap melihat kolom "Provinsi", "Kota/Kabupaten", dst. sebagai nama (bukan ID mentah), supaya tampilan tabel tidak berubah dari sudut pandang pengguna.
5. Sebagai admin yang mencari ("search") di menu "Kecamatan" berdasarkan nama provinsi atau nama kota/kabupaten, saya ingin pencarian tetap berfungsi walau kolom provinsi/kota_kabupaten sudah jadi ID, supaya fitur search tidak regresi.
6. Sebagai developer yang menjalankan `php artisan marketama:import`, saya ingin import CSV asli Marketama (yang masih berformat nama string, bukan ID) tetap bisa jalan — resolve nama → ID terjadi otomatis saat import, bukan manual.
7. Sebagai developer, jika CSV sumber punya nama provinsi/kota-kabupaten/kecamatan yang tidak ditemukan di tabel induk (typo atau data induk belum dimuat), saya ingin proses seed/import gagal dengan error yang jelas (nama + baris mana), bukan diam-diam menyimpan `NULL`, supaya masalah data ketahuan saat import, bukan saat dipakai.
8. Sebagai developer yang menulis migration baru, saya ingin kolom `*_id` dibuat lewat migration terpisah yang menambah kolom, resolve data lama (jika sudah ada), lalu drop kolom string lama — bukan mengedit migration awal yang sudah di-apply di environment lain.
9. Sebagai reviewer, saya ingin file seed CSV di `database/seeders/data/` (`kota_kabupaten.csv`, `kecamatan.csv`, `kelurahan.csv`) tetap konsisten dengan skema baru: tetap simpan kolom nama (karena itu format mentah dari Marketama), tapi proses load-nya yang menambahkan resolusi ID — supaya CSV tidak perlu diedit manual kolom per kolom.

## Implementation Decisions

**Migration baru** (`database/migrations/<timestamp>_normalisasi_relasi_wilayah.php`), tidak mengedit migration awal (`2026_10_22_000001_create_marketama_tables.php`):
- `kota_kabupaten`: tambah `provinsi_id` (`unsignedBigInteger`, nullable, index, FK opsional ke `provinsi.id`), drop `provinsi` (string).
- `kecamatan`: tambah `provinsi_id`, `kota_kabupaten_id`, drop `provinsi`, `kota_kabupaten` (string).
- `kelurahan`: tambah `provinsi_id`, `kota_kabupaten_id`, `kecamatan_id`, drop `provinsi`, `kota_kabupaten`, `kecamatan` (string).
- Kolom `kode` dan `nama_*` tetap seperti sekarang (tidak berubah).
- Tidak perlu foreign key constraint yang strict (`->references()->on()`) kalau tipe data SQLite tes in-memory bisa bermasalah — cek pola FK yang sudah dipakai di migration lain di file ini sebelum memutuskan; kalau belum ada pola FK constraint sama sekali di repo ini, cukup `unsignedBigInteger` + index biasa (konsisten dengan `source_id` yang juga cuma di-index, bukan FK constraint).

**Resolver nama → ID saat load CSV** — perluas `App\Support\CsvTableLoader`:
- Tambah parameter opsional, misal `CsvTableLoader::load(string $table, string $path, array $resolve = [], int $chunk = 1000)`, dengan `$resolve` berupa map `['kolom_csv' => ['table' => 'provinsi', 'column' => 'nama_provinsi', 'as' => 'provinsi_id']]` (dan untuk level berikutnya, scoped by parent id: `['kolom_csv' => 'kota_kabupaten', 'table' => 'kota_kabupaten', 'column' => 'nama_kota_kabupaten', 'as' => 'kota_kabupaten_id', 'scope_by' => 'provinsi_id']`).
- Sebelum loop insert, bangun lookup map in-memory per tabel induk yang relevan: `SELECT id, <column> FROM <table>` (plus kolom `scope_by` kalau ada), key dinormalisasi (`trim` + `mb_strtolower`).
- Saat membangun tiap baris: ambil nilai kolom CSV (nama), cari di lookup map (scoped by `provinsi_id`/`kota_kabupaten_id` baris yang sama bila relevan), isi kolom `as` dengan ID hasil lookup. Kalau tidak ketemu → lempar `RuntimeException` menyebutkan tabel, baris ke-berapa, dan nama yang tidak ditemukan (memenuhi user story 7).
- Kolom nama mentah (`provinsi`, `kota_kabupaten`, `kecamatan` di CSV) tidak lagi masuk ke `$keep` karena kolomnya sudah di-drop dari skema — otomatis diabaikan oleh filter `in_array($header[$i], $columns)` yang sudah ada, kecuali kalau lookup butuh nilainya sebelum di-drop dari array row (jadi ambil nilainya dari `$row`/`$header` langsung, bukan dari `$columns` tabel tujuan).
- `ReferenceDataSeeder::run()` pasti load tabel sesuai urutan di `TABLES` (provinsi → kota_kabupaten → kecamatan → kelurahan sudah berurutan di array saat ini) — urutan ini jadi prasyarat (parent harus sudah ada di DB sebelum anak di-resolve). Tambahkan komentar di `ReferenceDataSeeder` menjelaskan urutan ini sekarang load-bearing.
- `ImportMarketama` (artisan command `marketama:import`) harus memanggil `CsvTableLoader::load` dengan `$resolve` yang sama untuk ke-4 tabel ini, dan harus memastikan urutan import (`--only` parsial tetap bisa jalan kalau tabel induk sudah ada datanya dari sebelumnya — tidak perlu re-resolve ulang tabel induk).

**Tampilan di `MenuController` / `config/marketama.php`** — supaya menu "Kota Kabupaten", "Kecamatan", "Kelurahan" tetap menampilkan nama (bukan ID):
- Tambah key baru di entry `tables.<table>` pada `config/marketama.php`, misal `'joins' => [['table' => 'provinsi', 'local_key' => 'provinsi_id', 'foreign_key' => 'id', 'select' => ['nama_provinsi' => 'provinsi']]]`.
- `MenuController@show`: kalau `tables.<table>.joins` ada, bangun query dengan `->join(...)` dan `->select($table.'.*', 'provinsi.nama_provinsi as provinsi', ...)` sebelum filter search; kolom alias (`provinsi`, `kota_kabupaten`) dipakai di `columns` config (label tetap sama seperti sekarang: `'provinsi' => 'Provinsi'`) supaya tidak perlu ubah frontend (`Menu/Show.vue`, `DataTable.vue`).
- Search (`$search`) yang sekarang `foreach (array_keys($columns) as $column) { $q->orWhere($column, 'like', ...) }` perlu pindah ke alias hasil join untuk kolom yang di-join (pakai nama alias, bukan `provinsi_id`), supaya cari "Papua Barat Daya" di menu Kota Kabupaten tetap jalan (user story 5). Untuk PostgreSQL (`ILIKE`), pastikan alias ikut di-wrap dengan benar.
- Pagination/order tetap pakai `id` tabel utama (sudah ada `->orderBy('id')`), pastikan tidak ambigu setelah JOIN (qualify jadi `"{$table}.id"`).

## Testing Decisions

Seam: HTTP response lewat route generik `/{slug}` (sama seperti `MenuTest.php` yang sudah ada), assert lewat `assertInertia`, bukan query DB manual untuk re-cek apa yang controller sudah query.

- `tests/Feature/MenuTest.php` (atau file baru `WilayahMenuTest.php` kalau dirasa terlalu padat di satu file):
  - Seed `provinsi` 1 baris, lalu `kota_kabupaten` 1 baris dengan `provinsi_id` menunjuk ke baris provinsi tadi → `GET /kota_kabupaten` → assert kolom `provinsi` di response berisi nama provinsi (bukan ID), lewat `tables.0.rows.data.0.provinsi`.
  - Ulangi pola serupa untuk `/kecamatan` (2 level: provinsi + kota_kabupaten) dan `/kelurahan` (3 level).
  - Search: `GET /kota_kabupaten?q=<nama_provinsi>` → assert baris yang namanya cocok tetap muncul (`tables.0.rows.total`), memvalidasi search ikut JOIN.
- Unit test baru untuk `CsvTableLoader` (`tests/Unit/CsvTableLoaderTest.php` kalau belum ada, atau tambah ke yang sudah ada kalau ketemu): CSV kecil in-memory/temp file dengan kolom nama provinsi → assert baris hasil `load()` di tabel tujuan punya `provinsi_id` yang benar, dan assert `RuntimeException` dilempar kalau nama provinsi di CSV tidak ada di tabel `provinsi`.
- `ReferenceDataSeeder` end-to-end: cukup diverifikasi lewat `php artisan migrate --seed` dijalankan manual (bukan bagian dari test suite otomatis, karena re-generate CSV nyata ada di langkah implementasi terpisah) — catat di PR description, bukan test otomatis.

## Out of Scope

- Tabel laporan (`lap_absensi_koordinator`, `lap_join_visit`, `lap_outlet_oss`, `lap_penjualan_barang_terbaik_bulanan`, dll.) dan `master_depo` yang juga punya kolom `provinsi`/`kota_kab`/`kecamatan`/`kelurahan_desa` sebagai string. Kolom-kolom itu scara desain adalah hasil laporan/export yang sudah flat dari sistem sumber (Marketama), bukan tabel master referensi — menormalkannya akan mengubah bentuk data laporan apa adanya dan di luar masalah yang dilaporkan.
- `city_group` (tidak punya relasi ke provinsi/kota_kabupaten sama sekali, jadi tidak terdampak).
- Mengubah `routes/web.php`, `Menu/Show.vue`, atau komponen UI (`DataTable.vue`) — perubahan dibatasi ke query/controller supaya tampilan existing tidak berubah.
- Menambahkan foreign key constraint database yang strict (`ON DELETE`/`ON UPDATE`) kecuali pola serupa sudah ada di repo ini — perlu dicek dulu sebelum implementasi, bukan diasumsikan di spec ini.
- Memperbaiki/menulis ulang isi CSV di `database/seeders/data/kota_kabupaten.csv`, `kecamatan.csv`, `kelurahan.csv` secara manual — perubahan dilakukan di level kode loader (resolver), bukan isi file CSV, karena CSV tetap merepresentasikan format mentah dari Marketama.

## Further Notes

- Tabel `kota_kabupaten`, `kecamatan`, `kelurahan`, `provinsi` termasuk "data referensi tanpa data pribadi" (`ReferenceDataSeeder`) sehingga CSV-nya ikut masuk repo (`database/seeders/data/`) — berbeda dari tabel transaksi/karyawan/outlet yang sengaja tidak masuk repo karena data pribadi (lihat `CLAUDE.md`).
- Urutan load tabel di `ReferenceDataSeeder::TABLES` (provinsi sebelum kota_kabupaten sebelum kecamatan sebelum kelurahan) sudah benar untuk kebutuhan resolver ini, tapi sifatnya jadi implisit load-bearing setelah perubahan ini — perlu komentar penjelas di kode agar tidak ada yang mengacak urutannya di kemudian hari.
- `marketama_csv_import_1-22okt2026.zip` (sumber data asli, disebut user di awal percakapan) diasumsikan punya format kolom yang sama dengan CSV di `database/seeders/data/` saat ini (nama, bukan ID) — kalau format sumber asli ternyata sudah menyertakan ID, resolver ini jadi lebih sederhana (tidak perlu name-matching), tapi itu perlu dicek ulang terhadap file zip aslinya sebelum implementasi dimulai.
