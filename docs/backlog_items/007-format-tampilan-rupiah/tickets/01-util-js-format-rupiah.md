## Parent

docs/backlog_items/007-format-tampilan-rupiah/spec.md

## What to build

Buat `resources/js/utils/currency.js` yang mengekspor fungsi `formatRupiah(value)`:

1. Terima `value` berupa `number`, string numerik (boleh berdesimal, mis. `"1500000.50"`), `null`, atau string kosong.
2. Kembalikan string berformat `Rp 15.000` (prefix `Rp`, spasi, pemisah ribuan titik, dibulatkan ke rupiah penuh tanpa desimal).
3. Nilai negatif → tanda minus di depan prefix, mis. `-Rp 50.000` (bukan `Rp -50.000`).
4. `value` yang `null`, string kosong, atau tidak bisa diparse sebagai angka → kembalikan `-`.

## Acceptance criteria

- [ ] `resources/js/utils/currency.js` ada dan mengekspor `formatRupiah`.
- [ ] `formatRupiah(15000)` dan `formatRupiah('15000')` → `'Rp 15.000'`.
- [ ] `formatRupiah(1500000.5)` → `'Rp 1.500.001'` atau `'Rp 1.500.000'` sesuai aturan pembulatan yang dipilih saat implementasi (dokumentasikan arah pembulatan di PR — konsisten, tidak flaky).
- [ ] `formatRupiah(-50000)` → `'-Rp 50.000'`.
- [ ] `formatRupiah(0)` → `'Rp 0'`.
- [ ] `formatRupiah(null)` dan `formatRupiah('')` → `'-'`.
- [ ] `formatRupiah('bukan-angka')` → `'-'` (tidak melempar exception).
- [ ] Ada test (Vitest/Jest kalau sudah tersedia di proyek; kalau belum ada test runner JS, dokumentasikan di PR kenapa test ditulis manual/di-skip) yang menutupi kasus-kasus di atas.

## Blocked by

None (can start immediately).
