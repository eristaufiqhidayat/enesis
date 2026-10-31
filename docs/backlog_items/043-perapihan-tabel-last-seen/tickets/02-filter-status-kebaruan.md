# Filter Status kebaruan di Last Seen

## Parent

Spec: [docs/backlog_items/043-perapihan-tabel-last-seen/spec.md](../spec.md)

## What to build

Dropdown "Semua status" di header tabel Last Seen yang menyaring baris menurut status kebaruan, dijalankan di SQL.

## Acceptance criteria

- [x] `?status=hari_ini|minggu|lama|belum` menyaring baris sesuai definisi status di spec, lintas halaman paginasi; kunci tak dikenal diabaikan.
- [x] Sales dengan titik HP saja, titik lama saja, atau keduanya dikelompokkan benar (waktu efektif = yang lebih baru).
- [x] Dropdown tampil di header, ikut tombol Cari, dan nilainya tertahan di URL.
- [x] Berlaku untuk Sales dan Koordinator.

## Implementation steps

- [x] Interface `SumberBerstatus`; `SumberLastSeen::pilihanStatus` dan `batasiStatus` (EXISTS ke `trx_tracking` UTC dan tabel `*_lokasi` zona aplikasi).
- [x] `MenuController`: `filter['status']`, terapkan ke query, payload `pilihan_status`.
- [x] `Menu/Show.vue`: Select status, `useAmbilData`, `submitSearch`, watch `props.filter`.
- [x] Feature test tiap status dan kombinasi sumber data.

## Blocked by

- 01-kolom-status-lokasi-dan-sembunyikan-teknis.md (label status dipakai bersama)
