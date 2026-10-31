# Drawer lebar: daftar kunjungan di kiri, penanda bernomor di peta

## Parent

Spec: [docs/backlog_items/044-daftar-kunjungan-di-drawer-riwayat/spec.md](../spec.md)

## What to build

Drawer Riwayat diperlebar dengan daftar kunjungan di kiri peta, penanda bernomor di peta, dan klik baris yang memusatkan peta.

## Acceptance criteria

- [x] Drawer memakai `size="xl"`; layout dua kolom (satu kolom di layar sempit).
- [x] Daftar menampilkan nomor, outlet, jam masuk–keluar, durasi, lencana Beli / Tidak beli / Di luar rute / Lokasi palsu; pesan bila tidak ada kunjungan.
- [x] Kolom daftar tersembunyi bila respons tanpa kunci `kunjungan` (Koordinator).
- [x] Penanda bernomor di peta (hijau beli, oranye tidak beli); klik baris memusatkan peta dan membuka popup.
- [x] Mengganti tanggal memuat ulang rute dan daftar; batas peta mencakup rute dan kunjungan.

## Implementation steps

- [x] `Support/rute.js`: `gambarKunjungan`; `RuteHarianPeta.vue`: layout, daftar, fokus; `app.css`: kelas `.rute-harian*` dan `.penanda-kunjungan*`.
- [x] `Menu/Show.vue`: `size="xl"`.
- [x] Vitest `RuteHarianPeta.spec.js`.

## Blocked by

- 01-kunjungan-di-endpoint-locations.md
