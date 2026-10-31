# Kolom Status (badge), kolom Lokasi (tautan peta), sembunyikan Baterai & Sumber

## Parent

Spec: [docs/backlog_items/043-perapihan-tabel-last-seen/spec.md](../spec.md)

## What to build

Tabel Last Seen Sales dan Koordinator menampilkan badge Status kebaruan dan tombol "Lihat peta" (drawer peta) menggantikan Latitude/Longitude, tanpa kolom Baterai dan Sumber.

## Acceptance criteria

- [x] Kolom tabel: Nama, Telepon, Depo, Koordinator, Terakhir Terlihat, Lokasi, Riwayat, Lokasi Palsu (Sejak dan Status digabung ke Terakhir Terlihat).
- [x] `status` per baris: "Hari ini", "1–7 hari lalu", "Lebih dari 7 hari", "Belum ada data" dengan batas yang sama dengan Peta Sebaran Sales; ditampilkan sebagai warna badge kolom Terakhir Terlihat (hijau / kuning / merah / abu-abu) berisi teks relatif, dengan waktu pasti sebagai tooltip.
- [x] Kolom Lokasi berisi tombol "Lihat peta" yang membuka drawer berisi peta posisi terakhir (bukan Google Maps) bila koordinat ada, kosong bila tidak.
- [x] Peta Sebaran Sales tetap menerima baterai dan sumber.

## Implementation steps

- [x] Config kolom kedua tabel; `SumberLastSeen::ubahBaris` menambah `status`.
- [x] `Menu/Show.vue`: slot `#cell-terakhir_terlihat` (badge) dan `#cell-lokasi`.
- [x] Feature test label status dan daftar kolom.

## Blocked by

None
