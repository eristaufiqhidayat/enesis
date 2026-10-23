# Util Format Tanggal & Datetime untuk Tampilan (dd Mmm yyyy[, HH:mm], mis. "01 Okt 2026" / "01 Okt 2026, 08:30")

## Problem Statement

Semua tabel data Marketama yang ditampilkan lewat jalur Inertia (`MenuController::show`, `app/Http/Controllers/MenuController.php:51-62`) mengambil baris mentah lewat `DB::table(...)` dan mengirimkannya langsung ke `resources/js/Pages/Menu/Show.vue`, yang dirender oleh organism `resources/js/Components/ui/organisms/DataTable.vue:36-39`. Komponen itu menampilkan setiap nilai kolom apa adanya (`{{ row[column] }}`), termasuk kolom bertipe tanggal/datetime (misalnya `tanggal_data`, `checkin_pertama_hari_ini`, dsb. — lihat `config/marketama.php`). Akibatnya nilai tanggal tampil dalam format mentah database (`Y-m-d` atau `Y-m-d H:i:s`, mis. `2026-10-01`) bukan format yang mudah dibaca pengguna Indonesia (`01 Okt 2026`).

Belum ada util bersama untuk ini di kedua stack rendering:
- Tidak ada folder `resources/js/utils/` atau helper JS apa pun untuk format tanggal (dicek: hanya `resources/js/app.js` yang ada di root `resources/js`).
- Tidak ada PHP helper/Blade directive untuk format tanggal di stack Blade (`resources/views/tracking/`, `resources/views/area/`), yang saat ini hanya meneruskan string tanggal mentah ke `<input type="date">` dan teks (`resources/views/tracking/history.blade.php:26,208`).

Tanpa util bersama, setiap halaman yang butuh menampilkan tanggal terpaksa menulis ulang logic format (melanggar aturan "Komponen UI (atomic design) — WAJIB dipakai" di `CLAUDE.md` yang menekankan reuse, dan berisiko inkonsisten antar halaman — misalnya satu halaman pakai locale `id-ID` lewat `Intl.DateTimeFormat`, halaman lain pakai string manipulation ad-hoc).

## Solution

Beberapa tabel Marketama punya kolom bertipe `date` (mis. `tanggal_data`) maupun `dateTime`/timestamp (mis. `created_at`, `updated_at`, `fiktif_created_at`, `original_created_at` — lihat `database/migrations/2026_10_22_000001_create_marketama_tables.php:610-1171`). Util harus menangani kedua jenis ini dengan dua fungsi terpisah (bukan satu fungsi yang menebak-nebak), supaya kolom tanpa komponen jam tidak menampilkan `00:00` palsu, dan kolom dengan komponen jam tidak kehilangan informasi jam:

1. **JS (stack Inertia/Vue):** `resources/js/utils/date.js` mengekspor dua fungsi:
   - `formatTanggal(value)` — untuk kolom bertipe `date` murni (`Y-m-d`). Mengembalikan `dd Mmm yyyy` (mis. `01 Okt 2026`).
   - `formatTanggalWaktu(value)` — untuk kolom bertipe `datetime`/`timestamp` (`Y-m-d H:i:s`). Mengembalikan `dd Mmm yyyy, HH:mm` (mis. `01 Okt 2026, 08:30`).

   Kedua fungsi memakai nama bulan singkatan Indonesia yang sama (`Jan`, `Feb`, `Mar`, `Apr`, `Mei`, `Jun`, `Jul`, `Agu`, `Sep`, `Okt`, `Nov`, `Des`), menerima `null`/string kosong/string tidak valid dan mengembalikan `-` untuk kasus itu (konsisten dengan placeholder kosong yang sudah dipakai, mis. `DataTable.vue:37` pakai `|| '-'`).
2. **PHP (stack Blade):** helper setara dengan dua method (`App\Support\DateFormatter::tanggal($value)` dan `::tanggalWaktu($value)`) untuk dipakai di `resources/views/tracking/`, `resources/views/area/`, dan halaman Blade lain yang menampilkan tanggal/datetime.
3. Util ini **tidak otomatis diterapkan** ke semua kolom — kolom tanggal/datetime harus ditandai secara eksplisit dan per jenis (misalnya dua daftar nama kolom per tabel, mirip pola `badgeColumns` yang sudah ada di `DataTable.vue:12`), supaya kolom teks yang kebetulan berisi string menyerupai tanggal tidak salah diformat, dan supaya kolom `date` tidak diproses oleh fungsi `datetime` (atau sebaliknya).

## User Stories

1. Sebagai pengguna admin yang melihat tabel data (misalnya `checkin_pagi`, `absensi_sales`), saya ingin kolom tanggal tampil sebagai `01 Okt 2026` bukan `2026-10-01`, supaya lebih mudah dibaca tanpa perlu menghitung urutan bulan.
2. Sebagai pengguna admin yang melihat kolom `created_at`/`updated_at` atau kolom datetime lain, saya ingin tampilannya `01 Okt 2026, 08:30` (tanggal + jam), supaya saya tidak kehilangan informasi jam yang penting untuk audit/urutan kejadian.
3. Sebagai developer yang menambah menu baru dengan kolom tanggal atau datetime, saya ingin memanggil satu util format yang sudah teruji untuk masing-masing jenis, bukan menulis ulang logic `Intl.DateTimeFormat`/`Carbon` setiap kali, supaya format tetap konsisten di seluruh aplikasi.
4. Sebagai developer yang mengerjakan halaman Blade (`tracking/`, `area/`), saya ingin helper PHP yang sama perilakunya dengan util JS, supaya tanggal/datetime yang ditampilkan konsisten di kedua stack rendering.

## Implementation Decisions

- Lokasi util JS: `resources/js/utils/date.js`, dua fungsi `formatTanggal(value)` (input `Y-m-d`) dan `formatTanggalWaktu(value)` (input `Y-m-d H:i:s`). Pakai `Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })` sebagai basis untuk bagian tanggal, lalu sesuaikan/cek hasil locale `id-ID` di browser target — kalau singkatan bulan dari `Intl` tidak persis cocok (`Mei`, `Agu`, `Des` kadang berbeda antar environment Node/browser), override lewat array nama bulan singkat manual supaya hasil selalu deterministik. Bagian jam pada `formatTanggalWaktu` memakai format 24 jam `HH:mm` (tanpa detik), dipisah dari tanggal dengan `, ` (mis. `01 Okt 2026, 08:30`).
- Lokasi helper PHP: `App\Support\DateFormatter` dengan dua method statis `tanggal($value)` dan `tanggalWaktu($value)`, dipakai langsung dari Blade (`{{ \App\Support\DateFormatter::tanggal($row->tanggal) }}`) atau didaftarkan sebagai dua Blade directive `@tanggal($value)` / `@tanggalWaktu($value)` di `AppServiceProvider` — pilih salah satu saat implementasi, konsisten dengan pola Blade component yang sudah ada.
- Input yang diterima: string `Y-m-d` (untuk `formatTanggal`/`tanggal`), string `Y-m-d H:i:s` (untuk `formatTanggalWaktu`/`tanggalWaktu`), instance `Carbon`/`DateTime` (untuk sisi PHP, kedua method), atau `null`/string kosong → kembalikan `-`. Kolom bertipe timestamp (`created_at`, `updated_at`, `fiktif_created_at`, `original_created_at`, dll. — semua didefinisikan sebagai `dateTime` di migrasi) memakai `formatTanggalWaktu`/`tanggalWaktu`, bukan fungsi `tanggal`.
- Untuk menandai kolom mana yang perlu diformat di `DataTable.vue`, tambah dua prop baru (`dateColumns` untuk kolom `date`, `datetimeColumns` untuk kolom `datetime`/timestamp), pola sama seperti `badgeColumns` — bukan menebak tipe dari nama kolom atau isi data.
- Tidak mengubah skema `config/marketama.php` dalam tiket ini kecuali untuk menambahkan metadata kolom tanggal/datetime per tabel jika diperlukan sebagai bagian dari penerapan `dateColumns`/`datetimeColumns`.

## Testing Decisions

- Unit test JS (atau test manual terdokumentasi jika proyek belum punya test runner JS — cek dulu apakah ada Vitest/Jest sebelum menulis test) untuk `formatTanggal` dan `formatTanggalWaktu`: input valid sesuai tipe masing-masing, `null`, string kosong, string tidak valid, dan input tipe "salah" diberikan ke fungsi yang salah (mis. string dengan jam diberikan ke `formatTanggal`).
- Test PHP (`tests/Unit/...`) untuk helper `DateFormatter::tanggal` dan `::tanggalWaktu` dengan kasus yang sama (string, `Carbon`, `null`).

## Out of Scope

- Menerapkan `dateColumns`/`datetimeColumns`/pemanggilan util ini ke setiap menu yang punya kolom tanggal/datetime satu per satu — itu pekerjaan rollout terpisah per menu, di luar tiket pembuatan util ini.
- Mengubah format tanggal pada filter input (`<input type="date">` di `tracking/history.blade.php:26`) — input HTML date tetap perlu format `Y-m-d` untuk browser, util ini hanya untuk tampilan read-only.
- Internasionalisasi (locale selain Indonesia) — util ini khusus format Indonesia (`Jan`, `Feb`, ..., `Des`).
