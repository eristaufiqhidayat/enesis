# Popover lengkap dan alasan tidak beli yang rapi

## Parent

Spec: [docs/backlog_items/046-popover-lengkap-penanda-kunjungan/spec.md](../spec.md)

## What to build

`KunjunganHarian` menghasilkan alasan tidak beli yang rapi (label HP, teks bebas, dan `keterangan_lama` data lama), dan popover penanda kunjungan menampilkan semua info yang tersedia.

## Acceptance criteria

- [x] `keterangan`: label dari `config('mobile.alasan_tidak_beli')` untuk kode HP, digabung " — " dengan teks bebas bila ada; `keterangan_lama` untuk data lama; null bila tidak ada.
- [x] Popover memuat: nomor kunjungan dalam lingkaran berwarna sama dengan penanda di peta dan nama outlet, jam dan durasi, hasil dan alasan, lencana di luar rute / lokasi palsu, perjalanan dari kunjungan sebelumnya, jarak ke toko, dan tombol Lihat di Google Map (tanpa koordinat sebagai teks); baris yang datanya kosong tidak ditampilkan.
- [x] Isi popover di-escape.

## Implementation steps

- [x] `KunjunganHarian`: pilih `keterangan_lama`, bentuk `keterangan`.
- [x] `popupKunjungan` di `Support/rute.js`.
- [x] Feature test dan Vitest.

## Blocked by

None
