## Parent

docs/backlog_items/002-perbaikan-halaman-checkin-pagi/spec.md

## What to build

Tambah 3 card KPI di atas tabel halaman `checkin_pagi`: "Sudah Absen", "Belum Absen", dan "Terlambat", yang merekap data untuk tanggal yang sedang difilter (dari ticket filter tanggal backend). "Sudah Absen"/"Belum Absen" dihitung dari kolom `checkin_pertama_hari_ini` di tabel `checkin_pagi` sendiri; "Terlambat" dihitung dari tabel `absensi_sales_terlambat` yang sudah ada (menu "Absensi Sales Terlambat"), dicocokkan ke tanggal yang sama — bukan heuristik baru.

Sebelum implementasi, cek format kolom `tanggal` (string) di `absensi_sales_terlambat` terhadap data sampel nyata, untuk memastikan pencocokan ke format `Y-m-d` filter tanggal `checkin_pagi` benar (lihat catatan asumsi di spec).

## Acceptance criteria

- [ ] Halaman `/checkin_pagi` menampilkan 3 card KPI (Sudah Absen, Belum Absen, Terlambat) di atas tabel, dibangun dari komponen `Card.vue` yang sudah ada.
- [ ] Ketiga angka dihitung lewat `COUNT` query untuk keseluruhan tanggal terpilih (bukan dari baris yang sedang ditampilkan di satu halaman paginasi).
- [ ] Mengganti tanggal lewat date picker (dari ticket sebelumnya) memperbarui ketiga angka sesuai tanggal baru.
- [ ] Angka "Terlambat" konsisten dengan jumlah baris di menu "Absensi Sales Terlambat" untuk tanggal yang sama (diverifikasi manual dengan data seed/sampel).
- [ ] Halaman lain yang tidak punya `date_filter_column` tidak menampilkan card KPI sama sekali.
- [ ] Feature test baru membuktikan ketiga angka KPI benar untuk data seed yang mencakup kombinasi sudah absen/belum absen/terlambat pada tanggal yang sama.

## Blocked by

- Ticket "Filter tanggal backend checkin_pagi"
