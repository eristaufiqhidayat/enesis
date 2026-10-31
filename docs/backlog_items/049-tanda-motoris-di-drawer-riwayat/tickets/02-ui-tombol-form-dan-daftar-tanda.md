# UI drawer: tombol Tandai motoris, form, daftar tanda, cabut

## Parent

Spec: [docs/backlog_items/049-tanda-motoris-di-drawer-riwayat/spec.md](../spec.md)

## What to build

Bagian "Tanda motoris" di drawer Riwayat (Sales) berisi tombol Tandai motoris, form kategori dan catatan, daftar tanda pada tanggal yang dilihat, dan tombol Cabut.

## Acceptance criteria

- [x] Hanya tampil di drawer Sales; drawer Koordinator tidak berubah.
- [x] Tombol Tandai motoris hanya tampil bila `bisa_tambah`; form memilih kategori dan mengisi catatan; simpan mengirim POST dengan CSRF lalu memuat ulang daftar.
- [x] Galat validasi tampil di form.
- [x] Daftar memuat kategori, catatan, pemberi, dan waktu; ikut berganti saat tanggal drawer diganti.
- [x] Tombol Cabut hanya pada tanda dengan `bisa_cabut`, dengan konfirmasi.

## Implementation steps

- [x] `Pages/Menu/partials/TandaMotoris.vue` + `TandaMotoris.spec.js`.
- [x] Pasang di `RuteHarianPeta.vue` (prop `tandaUrl`) dan isi prop dari `Menu/Show.vue` untuk tabel Sales; kelas gaya di `app.css`.

## Blocked by

- 01-tabel-endpoint-hak-dan-audit.md
