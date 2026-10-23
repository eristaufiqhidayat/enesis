## Parent

docs/backlog_items/003-checkin-pertama-hari-ini-datetime/spec.md

## What to build

Tambah mekanisme normalisasi nilai datetime saat impor CSV lewat `marketama:import`, supaya nilai mentah kolom `checkin_pertama_hari_ini` (dan kolom datetime serupa di tabel lain di masa depan) diparse ke datetime yang valid sebelum disimpan, bukan disisipkan apa adanya. Mekanisme ini dikonfigurasi secara generik (misal daftar kolom datetime per tabel), mengikuti pola opsi per-halaman yang sudah ada di `config/marketama.php` — bukan hardcode nama tabel/kolom di loader CSV.

**Sebelum coding dimulai**: verifikasi format asli kolom `checkin_pertama_hari_ini` dari sampel data nyata Marketama (lewat tim/akses yang punya datanya) — repo ini tidak menyertakan data transaksi (lihat `CLAUDE.md`), jadi format sumber (jam saja, datetime penuh, format Indonesia, dll.) belum diketahui dan tidak boleh ditebak.

## Acceptance criteria

- [ ] Format asli kolom `checkin_pertama_hari_ini` pada data sampel nyata Marketama sudah diverifikasi dan dicatat (di tiket ini atau PR-nya) sebelum normalizer diimplementasikan sesuai format itu.
- [ ] Nilai CSV yang valid menurut format yang terverifikasi dinormalisasi menjadi datetime (`Y-m-d H:i:s`) sebelum disisipkan ke tabel `checkin_pagi`.
- [ ] Nilai CSV yang kosong tetap menjadi `NULL`, konsisten dengan perilaku `CsvTableLoader` yang sudah ada untuk kolom lain.
- [ ] Nilai CSV yang tidak bisa diparse ke format yang diharapkan ditangani dengan jelas (didokumentasikan apakah jadi `NULL` + dicatat, atau baris ditolak) — bukan menyebabkan error tak tertangani atau tersimpan sebagai teks mentah di kolom datetime.
- [ ] Test membuktikan normalizer menghasilkan datetime yang benar untuk sampel nilai dari format yang terverifikasi, dan menangani nilai kosong/tidak valid sesuai keputusan di atas.
- [ ] `php artisan test` dan `vendor/bin/pint` lulus setelah perubahan.

## Blocked by

- `#11` (Migrasi kolom checkin_pertama_hari_ini ke datetime)
