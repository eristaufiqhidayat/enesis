## Parent

docs/backlog_items/002-perbaikan-halaman-checkin-pagi/spec.md

## What to build

Tambah 3 card KPI di atas tabel halaman `checkin_pagi`: "Sudah Absen", "Belum Absen", dan "Terlambat", yang merekap data untuk tanggal yang sedang difilter (dari ticket filter tanggal backend). "Sudah Absen"/"Belum Absen" dihitung dari kolom `checkin_pertama_hari_ini` di tabel `checkin_pagi` sendiri; "Terlambat" dihitung dari tabel `absensi_sales_terlambat` yang sudah ada (menu "Absensi Sales Terlambat"), dicocokkan ke tanggal yang sama — bukan heuristik baru.

Sebelum implementasi, cek format kolom `tanggal` (string) di `absensi_sales_terlambat` terhadap data sampel nyata, untuk memastikan pencocokan ke format `Y-m-d` filter tanggal `checkin_pagi` benar (lihat catatan asumsi di spec).

## Acceptance criteria

- [x] Halaman `/checkin_pagi` menampilkan 3 card KPI (Sudah Absen, Belum Absen, Terlambat) di atas tabel, dibangun dari komponen `Card.vue` yang sudah ada.
- [x] Ketiga angka dihitung lewat `COUNT` query untuk keseluruhan tanggal terpilih (bukan dari baris yang sedang ditampilkan di satu halaman paginasi).
- [x] Mengganti tanggal lewat date picker (dari ticket sebelumnya) memperbarui ketiga angka sesuai tanggal baru.
- [ ] Angka "Terlambat" konsisten dengan jumlah baris di menu "Absensi Sales Terlambat" untuk tanggal yang sama (diverifikasi manual dengan data seed/sampel). — **belum**, lihat catatan status di bawah.
- [x] Halaman lain yang tidak punya `date_filter_column` tidak menampilkan card KPI sama sekali.
- [x] Feature test baru membuktikan ketiga angka KPI benar untuk data seed yang mencakup kombinasi sudah absen/belum absen/terlambat pada tanggal yang sama.

## Blocked by

- Ticket "Filter tanggal backend checkin_pagi"

## Status (implementasi 2026-10-23)

Semua acceptance criteria terpenuhi **kecuali** verifikasi konsistensi angka "Terlambat" dengan data nyata: repo/database lokal tidak punya sampel `absensi_sales_terlambat`, dan `docs/PRD.md` baris 74 menyebut CSV asli Marketama bisa menyimpan tanggal sebagai teks tampilan (bukan `Y-m-d`). Implementasi saat ini mencocokkan `absensi_sales_terlambat.tanggal` secara exact-string ke `Y-m-d` — kalau format produksi berbeda, angka "Terlambat" akan selalu 0 tanpa error. Dicatat sebagai catatan terbuka, belum dibuat tiket susulan (keputusan pengguna, 2026-10-23).
