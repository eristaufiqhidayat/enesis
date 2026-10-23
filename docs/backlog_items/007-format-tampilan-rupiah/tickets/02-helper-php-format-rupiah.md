## Parent

docs/backlog_items/007-format-tampilan-rupiah/spec.md

## What to build

Buat helper PHP setara untuk stack Blade (`resources/views/tracking/`, `resources/views/area/`):

1. Tambahkan `App\Support\CurrencyFormatter` dengan method statis `rupiah($value)` yang menerima `int`, `float`, string numerik, `null`, atau string kosong, dan mengembalikan string format yang **sama persis** dengan util JS di tiket 01 (`Rp 15.000`, pembulatan dan penanganan negatif konsisten dengan `formatRupiah`).
2. Kembalikan `-` untuk input kosong/`null`/tidak valid (sama seperti util JS).
3. Daftarkan Blade directive `@rupiah($value)` (di `AppServiceProvider::boot()`) yang memanggil helper ini, supaya pemakaian di view cukup `@rupiah($row->nominal)`.

## Acceptance criteria

- [ ] `App\Support\CurrencyFormatter::rupiah(...)` ada dan dites di `tests/Unit/CurrencyFormatterTest.php` (atau nama senada) untuk: int, float, string numerik, nol, negatif, `null`, string kosong, string tidak valid.
- [ ] Hasil format sama persis dengan util JS pada tiket 01 untuk input yang sama (mis. `15000` → `'Rp 15.000'`, `-50000` → `'-Rp 50.000'` di kedua sisi).
- [ ] Blade directive `@rupiah($value)` terdaftar dan berfungsi.
- [ ] `php artisan test` dan `vendor/bin/pint` lulus setelah perubahan.

## Blocked by

None (can start immediately — tidak bergantung pada tiket 01, hanya harus sinkron hasil akhirnya, termasuk arah pembulatan desimal).
