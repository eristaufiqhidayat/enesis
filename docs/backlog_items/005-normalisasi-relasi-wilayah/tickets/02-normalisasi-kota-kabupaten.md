## Parent

Bagian dari [#16](https://github.com/eristaufiqhidayat/enesis/issues/16) — lihat spec lengkap di `docs/backlog_items/005-normalisasi-relasi-wilayah/spec.md`.

## What to build

Tabel `kota_kabupaten` saat ini menyimpan nama provinsi induknya sebagai string (`provinsi`), bukan foreign key. Tiket ini menormalisasi tabel ini menjadi `provinsi_id`, dan sekaligus membangun dukungan generik di `MenuController`/`config/marketama.php` untuk JOIN + search lewat relasi — dukungan generik ini akan dipakai lagi oleh tiket `kecamatan` dan `kelurahan` berikutnya tanpa perlu dibangun ulang.

Lingkup:
- Migration baru (bukan edit migration awal) yang menambah `provinsi_id` (`unsignedBigInteger`, nullable, index — tanpa FK constraint, konsisten dengan pola `source_id` dan tabel lain di repo ini) pada `kota_kabupaten`, lalu drop kolom string `provinsi`.
- `ReferenceDataSeeder` dan `ImportMarketama` (`marketama:import`) memanggil `CsvTableLoader::load` untuk `kota_kabupaten` dengan konfigurasi resolve dari tiket #1, me-resolve kolom CSV `provinsi` (nama) menjadi `provinsi_id`.
- Tambahkan komentar di `ReferenceDataSeeder` yang menjelaskan bahwa urutan `provinsi` sebelum `kota_kabupaten` di `TABLES` sekarang load-bearing (parent harus sudah ada di DB sebelum anak di-resolve).
- `config/marketama.php`: tambah key `joins` pada entry `tables.kota_kabupaten` yang mendeklarasikan JOIN ke `provinsi` dan alias kolom hasil join supaya tetap tampil sebagai `provinsi` di tabel (label kolom tidak berubah dari sudut pandang pengguna).
- `MenuController@show`: bangun dukungan generik untuk `tables.<table>.joins` — build query dengan `->join(...)`, qualify `orderBy('id')` jadi `{table}.id` supaya tidak ambigu, dan arahkan pencarian (`$search`) untuk kolom hasil join ke alias-nya (bukan ke kolom `*_id` mentah), termasuk varian `ILIKE` untuk PostgreSQL.
- `--only` parsial pada `ImportMarketama` tetap bisa jalan kalau tabel induk (`provinsi`) sudah ada datanya dari sebelumnya (tidak perlu re-resolve ulang tabel induk).

Tidak ada perubahan di `routes/web.php`, `Menu/Show.vue`, atau `DataTable.vue` — perubahan dibatasi ke migration/seeder/import/controller/config.

## Acceptance criteria

- [ ] Migration baru menambah `kota_kabupaten.provinsi_id` dan drop kolom string `kota_kabupaten.provinsi`.
- [ ] `php artisan migrate --seed` berhasil mengisi `provinsi_id` yang valid untuk semua baris `kota_kabupaten` dari CSV referensi di repo.
- [ ] `marketama:import` berhasil me-resolve `provinsi_id` saat impor CSV asli Marketama (format nama string, bukan ID).
- [ ] Buka menu "Kota Kabupaten" di sidebar (`GET /kota_kabupaten`) tetap menampilkan kolom "Provinsi" sebagai nama (bukan ID mentah).
- [ ] Pencarian (`GET /kota_kabupaten?q=<nama_provinsi>`) tetap menemukan baris yang provinsinya cocok, walau kolom `provinsi` sudah jadi `provinsi_id`.
- [ ] Nama provinsi di CSV yang tidak ditemukan di tabel `provinsi` membuat proses seed/import gagal dengan error jelas (bukan `NULL` diam-diam) — mewarisi perilaku resolver dari tiket #1.
- [ ] Feature test baru (`tests/Feature/MenuTest.php` atau `WilayahMenuTest.php`) memverifikasi nama provinsi tampil benar dan search ikut JOIN, lewat `assertInertia` pada route `/kota_kabupaten`.

## Blocked by

- `#17` (resolver nama → ID di `CsvTableLoader` harus ada lebih dulu)
