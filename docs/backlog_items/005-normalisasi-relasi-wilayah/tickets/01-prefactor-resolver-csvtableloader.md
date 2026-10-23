## Parent

Bagian dari [#16](https://github.com/eristaufiqhidayat/enesis/issues/16) — lihat spec lengkap di `docs/backlog_items/005-normalisasi-relasi-wilayah/spec.md`.

## What to build

`App\Support\CsvTableLoader::load()` saat ini murni mencocokkan nama kolom CSV dengan kolom tabel tujuan (`Schema::getColumnListing`) lalu insert apa adanya — tidak ada mekanisme untuk mengubah nilai kolom (mis. resolve nama → ID) sebelum insert. Tiket ini menambahkan kemampuan resolver nama → ID ke loader, sebagai prasyarat sebelum tabel wilayah mana pun dinormalisasi.

Tambahkan parameter opsional pada `CsvTableLoader::load()` untuk mendeklarasikan kolom apa yang perlu di-resolve dari nama ke ID lookup tabel induk, dengan dukungan scoping (lookup dibatasi oleh ID kolom lain di baris yang sama, untuk kasus nama anak yang sama di induk berbeda). Sebelum loop insert, loader membangun lookup map in-memory dari tabel induk terkait (`SELECT id, <kolom_nama>`, dinormalisasi `trim` + `mb_strtolower`). Saat nama di baris CSV tidak ditemukan di lookup map, loader melempar `RuntimeException` yang menyebutkan tabel, baris keberapa, dan nama yang tidak ditemukan — bukan menyimpan `NULL` diam-diam.

Tiket ini murni membangun kapabilitas di `CsvTableLoader` — belum ada pemanggil (`ReferenceDataSeeder`, `ImportMarketama`) yang memakainya. Tidak ada migration atau perubahan skema di tiket ini.

## Acceptance criteria

- [ ] `CsvTableLoader::load()` menerima parameter resolve opsional untuk memetakan kolom CSV nama → kolom ID tujuan lewat lookup ke tabel induk, dengan dukungan scoping oleh kolom ID lain di baris yang sama.
- [ ] Lookup key dinormalisasi (`trim` + `mb_strtolower`) supaya tidak sensitif kapitalisasi/spasi.
- [ ] Nama yang tidak ditemukan di tabel induk melempar `RuntimeException` yang menyebutkan tabel, nomor baris, dan nama yang tidak ditemukan.
- [ ] Pemanggilan `load()` tanpa parameter resolve (seperti semua pemanggil existing saat ini) tetap berperilaku sama persis — tidak ada regresi pada 101+ tabel yang sudah dimuat lewat loader ini.
- [ ] `tests/Unit/CsvTableLoaderTest.php` baru: CSV kecil (file temp) dengan kolom nama → assert baris hasil `load()` punya kolom ID yang benar; dan assert `RuntimeException` dilempar saat nama di CSV tidak ada di tabel induk.

## Blocked by

None (can start immediately)
