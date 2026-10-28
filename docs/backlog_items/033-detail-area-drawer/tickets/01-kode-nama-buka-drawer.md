## Parent

docs/backlog_items/033-detail-area-drawer/spec.md

## What to build

Tracer-bullet end-to-end: kode dan nama area di tabel `master_area` menjadi tombol yang membuka drawer kanan. Isi drawer dibuat lengkap dalam tiket yang sama, karena data sudah ada di baris tabel.

## Acceptance criteria

- [ ] Kolom Kode dan Nama di `/master_area` tampil sebagai tombol teks (gaya tautan `.tautan-depo`) dan bisa difokus lewat keyboard.
- [ ] Klik kode atau nama membuka `Modal` dengan `placement="kanan"` dan judul nama area.
- [ ] Drawer menampilkan bagian Ringkasan, Lokasi, Depo, dan Pembaruan dari baris itu.
- [ ] Tombol tutup dan Esc menutup modal; state area terpilih dikosongkan.
- [ ] Kolom lain di tabel tidak berubah.

## Implementation steps

- [ ] `resources/js/Pages/Menu/Show.vue`: tambah `ref` area terpilih, slot `#cell-kode` dan `#cell-nama` untuk tabel `master_area`, dan `<Modal>` yang dibuka saat area terpilih.
- [ ] `resources/js/Pages/Menu/partials/DetailArea.vue`: infografis area (Ringkasan, Lokasi, Depo, Pembaruan).

## Blocked by

Tidak ada.
