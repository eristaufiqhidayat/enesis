## Parent

docs/backlog_items/032-detail-depo-slider-infografis/spec.md

## What to build

Isi drawer dengan empat bagian infografis (Ringkasan, Lokasi, Tim & Kuota, Target & Insentif) yang tampil berurutan dan digulir. Tidak ada slide atau navigasi bertahap.

## Acceptance criteria

- [ ] Empat bagian tampil berurutan dalam satu drawer yang bisa digulir; setiap bagian memuat data dari baris depo.
- [ ] Tidak ada tombol navigasi atau indikator slide.
- [ ] Progress bar sales aktif dan koordinator sesuai rumus (nilai / kuota, maks 100%); kuota kosong atau nol menampilkan "Kuota belum diisi.".
- [ ] Insentif per poin dan minimal value transaksi tampil sebagai `Rp …`; nilai kosong tampil `-`.
- [ ] `DetailDepo.spec.js` lulus.

## Implementation steps

- [ ] `DetailDepo.vue`: definisikan bagian sebagai daftar dan render berurutan.
- [ ] Helper format angka dan rupiah dengan `Intl` `id-ID` di dalam komponen.
- [ ] Class komponen di `app.css` untuk kartu angka, batang progress, dan baris detail.
- [ ] Spec Vitest untuk bagian, progress, dan format.

## Blocked by

- docs/backlog_items/032-detail-depo-slider-infografis/tickets/01-nama-depo-buka-modal.md
