# UI tombol, form, dan daftar tanda outlet

## Parent

Spec: [docs/backlog_items/053-tanda-outlet/spec.md](../spec.md)

## What to build

Partial `TandaOutlet.vue` (form tandai + daftar tanda aktif + cabut) dipasang di baris kunjungan outlet pada `RuteHarianPeta.vue` dan di kolom aksi `outlet_all` (`Menu/Show.vue`).

## Acceptance criteria

- [x] Tombol "Tandai outlet" (aksi menambah tanda, di dalam drawer `TandaOutlet.vue`) hanya tampil bila `bisa_tambah`. Ikon pemicu per baris kunjungan ("Tandai outlet") sendiri selalu tampil bila kunjungan punya `outlet_id` — siapa pun dengan hak Lihat `outlet_all` boleh membuka drawer untuk melihat tanda yang sudah ada.
- [x] Form mengirim `kategori` dan `catatan` dengan header CSRF, memuat ulang daftar setelah berhasil, menampilkan galat validasi.
- [x] Tombol "Cabut" hanya tampil sesuai `bisa_cabut`, dengan konfirmasi sebelum mengirim.
- [x] Lencana "Ditandai" (ticket 02) tampil di baris kunjungan drawer dan popover peta, bukan hanya di `outlet_all`.

## Implementation steps

- [x] `Pages/Menu/partials/TandaOutlet.vue`.
- [x] Pasang di `RuteHarianPeta.vue` (ikon per baris kunjungan, drawer dalam Modal kedua di atas drawer Riwayat) dan `Menu/Show.vue` (kolom `ditandai` pada tabel `mk_outlet`, bukan `aksi` terpisah — konsisten dengan pola lencana klik-untuk-buka yang sudah ada seperti `tracking_last_seen_sales`).
- [x] Vitest `TandaOutlet.spec.js`; tambah kasus lencana & tombol di `RuteHarianPeta.spec.js`; tambah kasus lencana di `rute.spec.js` (popover peta).

## Blocked by

- [01-tabel-endpoint-hak-dan-audit.md](01-tabel-endpoint-hak-dan-audit.md)
- [02-lencana-ditandai-di-outlet-all-dan-kunjungan.md](02-lencana-ditandai-di-outlet-all-dan-kunjungan.md)
