## Parent

docs/backlog_items/002-perbaikan-halaman-checkin-pagi/spec.md

## What to build

Tambah date picker di halaman `Menu/Show.vue`, muncul hanya untuk halaman yang punya filter tanggal (ditandai lewat props dari ticket sebelumnya), terisi default tanggal hari ini, dan berfungsi berdampingan dengan form pencarian teks yang sudah ada — submit mengirim `tanggal` dan `q` sekaligus dalam satu request, tersimpan di query string.

## Acceptance criteria

- [ ] Date picker muncul di halaman `/checkin_pagi`, default terisi tanggal hari ini saat halaman dibuka pertama kali.
- [ ] Date picker TIDAK muncul di halaman lain yang tidak punya filter tanggal (misal `/provinsi`).
- [ ] Mengubah tanggal lalu submit memuat ulang tabel sesuai tanggal baru, mempertahankan nilai pencarian teks yang sedang diisi.
- [ ] Date picker dibangun dari komponen atom yang ada/baru di `resources/js/Components/ui/atoms/` (bukan `<input>` mentah di tengah halaman), konsisten dengan aturan atomic design di `CLAUDE.md`.
- [ ] Diverifikasi manual di browser (buka `/checkin_pagi`, ganti tanggal, submit) karena ini perubahan UI — sertakan hasil pengecekan ini di PR description.

## Blocked by

- Ticket "Filter tanggal backend checkin_pagi"
