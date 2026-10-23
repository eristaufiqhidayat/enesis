## Parent

Bagian dari [#16](https://github.com/eristaufiqhidayat/enesis/issues/16) — lihat spec lengkap di `docs/backlog_items/005-normalisasi-relasi-wilayah/spec.md`.

## What to build

Terapkan pola normalisasi yang sama ke tabel `kelurahan`, yang punya 3 level induk: `provinsi`, `kota_kabupaten`, dan `kecamatan`. Ini melengkapi relasi hierarki wilayah 4 level penuh (provinsi → kota/kab → kecamatan → kelurahan) sehingga semuanya bisa di-JOIN langsung dan di-drill-down tanpa string matching.

Lingkup:
- Migration baru yang menambah `provinsi_id`, `kota_kabupaten_id`, dan `kecamatan_id` (`unsignedBigInteger`, nullable, index, tanpa FK constraint) pada `kelurahan`, lalu drop kolom string `provinsi`, `kota_kabupaten`, `kecamatan`.
- `ReferenceDataSeeder` dan `ImportMarketama` me-resolve ketiga kolom. Resolusi `kecamatan_id` di-scope oleh `kota_kabupaten_id` (dan transitif `provinsi_id`) baris yang sama — `kecamatan.kota_kabupaten_id` dari tiket sebelumnya jadi prasyarat data untuk scoping ini.
- `config/marketama.php`: tambah `joins` pada entry `tables.kelurahan` untuk ketiga relasi, dengan alias kolom tetap `provinsi`, `kota_kabupaten`, `kecamatan` seperti sebelumnya.
- `--only` parsial pada `ImportMarketama` tetap jalan kalau tabel induk (`provinsi`, `kota_kabupaten`, `kecamatan`) sudah ada datanya dari sebelumnya.

Tidak ada perubahan di `routes/web.php`, `Menu/Show.vue`, atau `DataTable.vue`.

## Acceptance criteria

- [ ] Migration baru menambah `kelurahan.provinsi_id`, `kelurahan.kota_kabupaten_id`, `kelurahan.kecamatan_id`, drop kolom string `provinsi`, `kota_kabupaten`, `kecamatan` di tabel `kelurahan`.
- [ ] `php artisan migrate --seed` berhasil mengisi ketiga kolom ID dengan benar, termasuk scoping bertingkat (`kecamatan_id` di-scope `kota_kabupaten_id`).
- [ ] `marketama:import` berhasil me-resolve ketiga kolom ID saat impor CSV asli Marketama.
- [ ] Buka menu "Kelurahan" (`GET /kelurahan`) tetap menampilkan kolom "Provinsi", "Kota/Kabupaten", "Kecamatan" sebagai nama.
- [ ] Pencarian (`GET /kelurahan?q=<nama_provinsi|kota_kabupaten|kecamatan>`) tetap menemukan baris yang cocok lewat JOIN.
- [ ] Nama di CSV yang tidak ditemukan di tabel induk (atau ambigu scoping-nya) membuat proses seed/import gagal dengan error jelas.
- [ ] Feature test baru memverifikasi relasi 3 level penuh tampil benar lewat `assertInertia` pada route `/kelurahan`.

## Blocked by

- `#20` (`kecamatan.kota_kabupaten_id` harus sudah ada dan terisi untuk men-scope resolusi `kecamatan_id` di tabel ini)
