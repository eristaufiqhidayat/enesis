# Seragamkan Tampilan Nilai Rupiah

## Problem Statement

Banyak tabel data Marketama punya kolom bertipe nilai uang — mis. `nominal` (beberapa tabel potongan/transaksi gaji, `config/marketama.php:1574,1586,1601,1614,1627`), `harga_beli`/`harga_jual` (`config/marketama.php:1966-1967`), `gaji_pokok`/`gaji_bersih`/`potongan`/`insentif_ec`/`insentif_absensi`/`insentif_per_poin` (`config/marketama.php:1696-1727,2008`), dan `price` (`config/marketama.php:2819,2852`) — tapi semuanya dirender mentah oleh `resources/js/Components/ui/organisms/DataTable.vue:36-39` (`{{ row[column] }}`), sama seperti kolom teks biasa. Nilai yang tersimpan sebagai angka database (mis. `15000`, `1500000.5`) tampil begitu saja tanpa pemisah ribuan, tanpa prefix `Rp`, dan tanpa aturan desimal yang konsisten.

Satu-satunya format angka yang sudah ada saat ini adalah `formatTotal` di `DataTable.vue:15-17` (`new Intl.NumberFormat('id-ID').format(total)`), yang khusus untuk menampilkan jumlah total baris di footer tabel — bukan untuk kolom nilai rupiah, dan tidak memakai prefix `Rp`. Tidak ada util bersama untuk format rupiah di kedua stack rendering (JS maupun PHP/Blade), jadi kalau dikerjakan ad-hoc per halaman berisiko inkonsisten: ada yang pakai titik sebagai pemisah ribuan, ada yang tanpa `Rp`, ada yang menyisakan desimal `.5` mentah, dsb. Ini melanggar aturan "Komponen UI (atomic design) — WAJIB dipakai" di `CLAUDE.md` yang menekankan reuse lewat satu util/komponen bersama, bukan duplikasi logic di tiap view.

## Solution

Buat util format rupiah yang tipis dan dipanggil dari satu tempat per stack, menghasilkan format Indonesia standar `Rp 15.000` (prefix `Rp`, spasi, pemisah ribuan titik, tanpa desimal untuk nilai bulat; nilai berdesimal dibulatkan ke rupiah penuh karena mata uang Rupiah tidak lazim ditampilkan dengan sen di UI admin ini):

1. **JS (stack Inertia/Vue):** `resources/js/utils/currency.js` mengekspor fungsi `formatRupiah(value)` yang menerima angka atau string numerik dari database (bisa `null`/kosong/negatif) dan mengembalikan string `Rp 15.000`, atau `-` kalau nilainya kosong/tidak valid (konsisten dengan placeholder kosong yang sudah dipakai di `DataTable.vue:37`).
2. **PHP (stack Blade):** helper setara (`App\Support\CurrencyFormatter::rupiah($value)`, atau Blade directive `@rupiah($value)`) untuk dipakai di `resources/views/tracking/`, `resources/views/area/`, dan halaman Blade lain yang menampilkan nilai uang.
3. Sambungkan util JS ke `DataTable.vue` lewat prop baru (mis. `currencyColumns`, mengikuti pola `badgeColumns`/`dateColumns` yang sudah/akan ada — lihat `docs/backlog_items/006-util-format-tanggal-tampilan/spec.md` untuk pola serupa), supaya kolom harus ditandai eksplisit per tabel, bukan ditebak dari nama kolom.

## User Stories

1. Sebagai pengguna admin yang melihat tabel `potongan_gaji`, `lap_harga_barang`, atau rekap gaji, saya ingin kolom nilai uang tampil sebagai `Rp 1.500.000` bukan `1500000`, supaya mudah dibaca tanpa menghitung digit.
2. Sebagai developer yang menambah menu baru dengan kolom nilai uang, saya ingin memanggil satu util `formatRupiah` yang sudah teruji, bukan menulis ulang `Intl.NumberFormat` atau string manipulation setiap kali, supaya format rupiah konsisten di seluruh aplikasi.
3. Sebagai developer yang mengerjakan halaman Blade, saya ingin helper PHP yang perilakunya sama dengan util JS, supaya nilai rupiah yang ditampilkan konsisten di kedua stack rendering.

## Implementation Decisions

- Lokasi util JS: `resources/js/utils/currency.js`, fungsi `formatRupiah(value)`. Pakai `Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 })` sebagai basis — verifikasi dulu bahwa hasilnya `Rp15.000` (tanpa spasi di beberapa environment `Intl`) vs `Rp 15.000` yang diinginkan; kalau tidak konsisten antar environment, format manual (prefix `'Rp '` + pemisah ribuan titik) supaya hasil deterministik.
- Lokasi helper PHP: `App\Support\CurrencyFormatter::rupiah($value)`, bisa didaftarkan juga sebagai Blade directive `@rupiah($value)` di `AppServiceProvider`, konsisten dengan pola yang dipakai untuk helper tanggal (lihat `006-util-format-tanggal-tampilan`).
- Input yang diterima: `int`, `float`, string numerik (termasuk yang mengandung desimal, mis. `"1500000.50"`), `null`, atau string kosong → `null`/kosong mengembalikan `-`; nilai desimal dibulatkan ke rupiah penuh (tanpa sen).
- Nilai negatif ditampilkan dengan tanda minus di depan prefix, mis. `-Rp 50.000` (bukan `Rp -50.000`), karena ada kolom seperti `potongan`/`potongan_gaji` yang mungkin disimpan negatif.
- Untuk menandai kolom mana yang perlu diformat di `DataTable.vue`, tambah prop baru `currencyColumns` (pola sama seperti `badgeColumns`) — bukan menebak tipe dari nama kolom atau isi data.
- Tidak mengubah skema `config/marketama.php` dalam tiket ini kecuali untuk menambahkan metadata kolom rupiah per tabel jika diperlukan sebagai bagian dari penerapan `currencyColumns`.

## Testing Decisions

- Unit test JS (Vitest/Jest kalau sudah tersedia; kalau belum ada test runner JS, dokumentasikan di PR kenapa test ditulis manual/di-skip) untuk `formatRupiah`: nilai bulat, nilai desimal, nol, negatif, `null`, string kosong, dan string tidak valid.
- Test PHP (`tests/Unit/...`) untuk helper `CurrencyFormatter::rupiah` dengan kasus yang sama.

## Out of Scope

- Menerapkan `currencyColumns`/pemanggilan util ini ke setiap menu yang punya kolom nilai uang satu per satu — itu pekerjaan rollout terpisah per menu, di luar tiket pembuatan util ini.
- Mengubah input form (kalau ada halaman input nilai uang di masa depan) — util ini hanya untuk tampilan read-only di tabel.
- Mata uang selain Rupiah — util ini khusus format Rupiah Indonesia.
