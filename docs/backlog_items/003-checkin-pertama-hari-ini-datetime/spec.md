# Checkin Pertama Hari Ini Disimpan Sebagai Datetime

## Problem Statement

Kolom `checkin_pertama_hari_ini` di tabel `checkin_pagi` saat ini bertipe `string(255)` (lihat migrasi `2026_10_22_000001_create_marketama_tables.php` baris 578). Ini adalah jam/waktu check-in pertama seorang sales pada hari itu, tapi karena disimpan sebagai teks bebas:

- Tidak ada jaminan formatnya konsisten (bisa `"08:05"`, `"08.05"`, datetime penuh, atau nilai lain apa pun yang ditulis apa adanya dari CSV Marketama).
- Logika "sudah absen vs belum absen" — dipakai di badge tabel (`resources/js/Components/ui/organisms/DataTable.vue`) dan di KPI card baru (`MenuController::kpiCheckinPagi`) — hanya mengecek *tidak kosong*, bukan apakah nilainya benar representasi waktu yang valid. Nilai sampah non-kosong (misal spasi tak terlihat yang bukan string kosong, atau teks tidak valid lain) akan dihitung "sudah absen".
- Tidak bisa diurutkan atau difilter secara kronologis (misal "siapa yang checkin setelah jam 9") karena perbandingan string, bukan waktu.
- Tidak konsisten dengan prinsip normalisasi data yang sudah digariskan di `docs/PRD.md` ("Data bernilai tampilan, bukan nilai mentah... tanggal Indonesia → `datetime`").

## Solution

Ubah kolom `checkin_pertama_hari_ini` menjadi tipe `datetime` (nullable), dan normalisasi nilai mentah dari sumber (CSV Marketama lewat `marketama:import`) ke format datetime yang valid saat diimpor, bukan disimpan apa adanya.

- Badge dan KPI "sudah absen/belum absen" tetap berdasarkan `NULL` vs terisi, tapi sekarang nilainya terjamin berupa datetime valid kalau terisi — bukan string sembarangan.
- Menyiapkan jalan untuk kebutuhan masa depan yang butuh waktu asli (urut berdasarkan jam checkin, filter "checkin setelah jam X", dsb.) tanpa parsing ulang string di setiap tempat yang membutuhkannya.

## User Stories

1. Sebagai developer yang menghitung KPI "Sudah Absen"/"Belum Absen", saya ingin kolom ini bertipe datetime, supaya hitungan tidak bergantung pada asumsi string kosong vs tidak kosong yang rapuh.
2. Sebagai developer yang mengimpor data dari Marketama, saya ingin ada normalisasi format waktu saat data masuk, supaya nilai yang tersimpan selalu datetime valid atau `NULL`, tidak pernah teks mentah yang tidak terduga.
3. Sebagai koordinator yang melihat tabel `checkin_pagi`, saya ingin badge "sudah absen" tetap tampil sama seperti sekarang (hijau jika ada waktu checkin, abu-abu "-" jika tidak), supaya perubahan ini tidak mengubah tampilan yang sudah ada.
4. Sebagai developer di masa depan yang ingin menambah fitur "urutkan berdasarkan jam checkin" atau "filter checkin setelah jam tertentu", saya ingin kolom ini sudah berupa datetime, supaya tidak perlu migrasi ulang atau parsing string saat fitur itu dibutuhkan.
5. Sebagai developer yang menjalankan test suite, saya ingin test membuktikan baik kasus data lama (string, lewat migrasi data) maupun data baru (hasil import yang sudah dinormalisasi) menghasilkan datetime yang benar atau `NULL` yang konsisten.

## Implementation Decisions

- **Migrasi baru**: ubah kolom `checkin_pertama_hari_ini` di tabel `checkin_pagi` dari `string(255)` menjadi `dateTime()->nullable()`, lewat migrasi `Schema::table('checkin_pagi', ...)->change()` (butuh `doctrine/dbal` atau pendekatan drop+add kolom tergantung driver — cek dukungan `change()` di SQLite untuk test environment).
- **Normalisasi saat impor**: `App\Support\CsvTableLoader` saat ini memasukkan nilai CSV apa adanya (`$value === '' ? null : $value`). Perlu mekanisme normalisasi per-kolom yang dikonfigurasi (misal daftar kolom datetime per tabel di `config/marketama.php`, dibaca generik oleh `CsvTableLoader`, mengikuti pola `date_filter_column` yang sudah ada), supaya nilai CSV diparse ke format datetime (`Y-m-d H:i:s`) sebelum disisipkan — bukan hardcode nama tabel/kolom di `CsvTableLoader`.
- **Format sumber harus diverifikasi lebih dulu**: belum ada contoh data nyata `checkin_pertama_hari_ini` di repo (tabel transaksi, tidak disertakan — lihat `CLAUDE.md`). Sebelum implementasi, cek sampel CSV asli dari Marketama untuk tahu format waktu yang benar-benar dipakai (jam saja `"08:05"`? datetime penuh? format Indonesia seperti kolom tanggal lain yang disebut PRD?). Spec ini tidak menebak formatnya.
- **Dampak ke kode yang sudah ada**: `MenuController::kpiCheckinPagi` dan `badgeVariant()` di `DataTable.vue` memakai pengecekan "kosong vs tidak kosong" — ini tetap valid untuk datetime (`NULL` vs terisi), tapi query `WHERE checkin_pertama_hari_ini != ''` di `MenuController` perlu disesuaikan jadi cukup `whereNotNull` saja (datetime tidak punya representasi "string kosong").

## Testing Decisions

- Seam: HTTP routes lewat `$this->get('/checkin_pagi', ...)` + `assertInertia`, mengikuti pola `tests/Feature/CheckinPagiKpiTest.php` dan `CheckinPagiFilterTest.php` yang sudah ada — seed data lewat `DB::table('checkin_pagi')->insert(...)` dengan nilai datetime, bukan string.
- Seam baru: normalisasi impor, diuji di level `CsvTableLoader` atau `ImportMarketama` (unit/feature test terpisah) — memastikan nilai mentah dari CSV sampel dinormalisasi jadi datetime yang benar, dan nilai yang gagal diparse ditangani dengan jelas (jadi `NULL` dengan log, atau menolak baris — keputusan ini butuh konfirmasi setelah format sumber diverifikasi).
- Test yang sudah ada (`CheckinPagiFilterTest`, `CheckinPagiKpiTest`) perlu disesuaikan seednya dari string jam (`'08:05'`) ke nilai datetime, dan tetap harus hijau setelah migrasi tipe kolom.

## Out of Scope

- Mengubah kolom tanggal/waktu string lain di tabel Marketama manapun selain `checkin_pertama_hari_ini` — pola normalisasi generik disiapkan supaya bisa dipakai ulang, tapi pengisian untuk kolom lain adalah pekerjaan terpisah.
- Fitur baru yang memanfaatkan nilai datetime ini (urut berdasarkan jam, filter jam tertentu) — spec ini hanya menyiapkan tipe data yang benar, bukan menambah fitur yang memakainya.
- Migrasi data historis yang sudah tersimpan sebagai string tidak valid di database produksi (di luar repo) — strategi migrasi data produksi didiskusikan terpisah dengan product owner, mengikuti §8 `docs/PRD.md`.

## Further Notes

- Ini konsisten dengan prinsip normalisasi yang sudah digariskan di §5 dan §8 `docs/PRD.md` ("tanggal Indonesia → `datetime`"), tapi PRD itu ditulis untuk data referensi (CSV di `database/seeders/data`) — spec ini memperluas prinsip yang sama ke data transaksi yang diimpor lewat `marketama:import`.
- Karena belum ada sampel data `checkin_pertama_hari_ini` nyata, implementasi tiket pertama dari spec ini sebaiknya dimulai dengan verifikasi format sumber (lihat Implementation Decisions), bukan langsung menulis migrasi.
