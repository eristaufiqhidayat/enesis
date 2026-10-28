## Parent

docs/backlog_items/032-detail-depo-slider-infografis/spec.md

## What to build

Tracer-bullet end-to-end: nama depo di tabel `master_depo` menjadi tombol yang membuka drawer kanan dengan isi placeholder. Ini membuktikan jalur klik → state → modal → tutup sudah jalan sebelum infografis depo dibuat.

## Acceptance criteria

- [ ] Kolom Nama di `/master_depo` tampil sebagai tombol teks (gaya tautan) dan bisa difokus lewat keyboard.
- [ ] Klik nama depo membuka `Modal` dengan `placement="kanan"` (drawer full height dari kanan) dan judul nama depo.
- [ ] Tombol tutup dan Esc menutup modal; state depo terpilih dikosongkan.
- [ ] Kolom lain di tabel tidak berubah.

## Implementation steps

- [ ] `resources/js/Pages/Menu/Show.vue`: tambah `ref` depo terpilih, slot `#cell-nama` untuk tabel `master_depo`, dan `<Modal>` yang dibuka saat depo terpilih.
- [ ] `resources/css/app.css`: class komponen `.tautan-depo` untuk tombol nama depo di `@layer components`.
- [ ] `resources/js/Pages/Menu/partials/DetailDepo.vue`: kerangka komponen dengan isi placeholder.

## Blocked by

Tidak ada.
