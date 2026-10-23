## Parent

docs/backlog_items/006-util-format-tanggal-tampilan/spec.md

## What to build

Buat helper PHP setara untuk stack Blade (`resources/views/tracking/`, `resources/views/area/`):

1. Tambahkan `App\Support\DateFormatter` dengan dua method statis:
   - `tanggal($value)` — setara `formatTanggal` di tiket 01, untuk kolom bertipe `date`.
   - `tanggalWaktu($value)` — setara `formatTanggalWaktu` di tiket 01, untuk kolom bertipe `datetime`/timestamp (mis. `created_at`, `updated_at`, `fiktif_created_at`, `original_created_at` — lihat `database/migrations/2026_10_22_000001_create_marketama_tables.php:610-1171`).
2. Kedua method menerima string (`Y-m-d` untuk `tanggal`, `Y-m-d H:i:s` untuk `tanggalWaktu`), instance `Carbon`/`DateTimeInterface`, `null`, atau string kosong, dan mengembalikan string format yang **sama persis** dengan util JS di tiket 01 (`dd Mmm yyyy` / `dd Mmm yyyy, HH:mm`, nama bulan singkat Indonesia yang sama).
3. Kembalikan `-` untuk input kosong/`null`/tidak valid (sama seperti util JS).
4. Daftarkan dua Blade directive, `@tanggal($value)` dan `@tanggalWaktu($value)` (di `AppServiceProvider::boot()`), yang memanggil helper ini, supaya pemakaian di view cukup `@tanggal($row->tanggal_data)` / `@tanggalWaktu($row->created_at)`.

## Acceptance criteria

- [ ] `App\Support\DateFormatter::tanggal(...)` dan `::tanggalWaktu(...)` ada dan dites di `tests/Unit/DateFormatterTest.php` (atau nama senada) untuk: string, instance `Carbon`, `null`, string kosong, string tidak valid — masing-masing untuk kedua method.
- [ ] Hasil format sama persis dengan util JS pada tiket 01 untuk input tanggal/datetime yang sama (mis. `'2026-10-01'` → `'01 Okt 2026'`, `'2026-10-01 08:30:00'` → `'01 Okt 2026, 08:30'` di kedua sisi).
- [ ] Blade directive `@tanggal($value)` dan `@tanggalWaktu($value)` terdaftar dan berfungsi.
- [ ] `php artisan test` dan `vendor/bin/pint` lulus setelah perubahan.

## Blocked by

None (can start immediately — tidak bergantung pada tiket 01, hanya harus sinkron hasil akhirnya).
