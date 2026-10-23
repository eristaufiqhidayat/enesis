## Parent

Bagian dari [#16](https://github.com/eristaufiqhidayat/enesis/issues/16) — lihat spec lengkap di `docs/backlog_items/005-normalisasi-relasi-wilayah/spec.md`.

## What to build

Terapkan pola normalisasi yang sama dari tiket `kota_kabupaten` ke tabel `kecamatan`, yang punya 2 level induk: `provinsi` dan `kota_kabupaten`. Dukungan JOIN/search generik di `MenuController`/`config/marketama.php` sudah dibangun di tiket sebelumnya dan dipakai ulang di sini — tiket ini fokus pada migration, resolver, dan config untuk `kecamatan`.

Lingkup:
- Migration baru yang menambah `provinsi_id` dan `kota_kabupaten_id` (`unsignedBigInteger`, nullable, index, tanpa FK constraint) pada `kecamatan`, lalu drop kolom string `provinsi` dan `kota_kabupaten`.
- `ReferenceDataSeeder` dan `ImportMarketama` me-resolve kolom CSV `provinsi` → `provinsi_id` dan `kota_kabupaten` → `kota_kabupaten_id`. Resolusi `kota_kabupaten_id` di-scope oleh `provinsi_id` baris yang sama (supaya nama kota/kabupaten yang sama di provinsi berbeda tidak salah cocok) — `kota_kabupaten.provinsi_id` dari tiket sebelumnya jadi prasyarat data untuk scoping ini.
- `config/marketama.php`: tambah `joins` pada entry `tables.kecamatan` untuk kedua relasi (`provinsi`, `kota_kabupaten`), dengan alias kolom tetap `provinsi` dan `kota_kabupaten` seperti sebelumnya.
- `--only` parsial pada `ImportMarketama` tetap jalan kalau tabel induk (`provinsi`, `kota_kabupaten`) sudah ada datanya dari sebelumnya.

Tidak ada perubahan di `routes/web.php`, `Menu/Show.vue`, atau `DataTable.vue`.

## Acceptance criteria

- [ ] Migration baru menambah `kecamatan.provinsi_id` dan `kecamatan.kota_kabupaten_id`, drop kolom string `provinsi` dan `kota_kabupaten` di tabel `kecamatan`.
- [ ] `php artisan migrate --seed` berhasil mengisi kedua kolom ID dengan benar, termasuk `kota_kabupaten_id` yang di-scope oleh provinsi yang sama.
- [ ] `marketama:import` berhasil me-resolve kedua kolom ID saat impor CSV asli Marketama.
- [ ] Buka menu "Kecamatan" (`GET /kecamatan`) tetap menampilkan kolom "Provinsi" dan "Kota/Kabupaten" sebagai nama.
- [ ] Pencarian (`GET /kecamatan?q=<nama_provinsi atau nama_kota_kabupaten>`) tetap menemukan baris yang cocok lewat JOIN.
- [ ] Nama provinsi/kota-kabupaten di CSV yang tidak ditemukan di tabel induk (atau ambigu scoping-nya) membuat proses seed/import gagal dengan error jelas.
- [ ] Feature test baru memverifikasi relasi 2 level (`provinsi` + `kota_kabupaten`) tampil benar lewat `assertInertia` pada route `/kecamatan`.

## Blocked by

- `#18` (`kota_kabupaten.provinsi_id` harus sudah ada dan terisi untuk men-scope resolusi `kota_kabupaten_id` di tabel ini)
