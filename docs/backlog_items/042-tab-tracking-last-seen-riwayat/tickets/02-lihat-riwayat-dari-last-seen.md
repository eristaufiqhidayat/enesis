# Tombol "Lihat riwayat" (drawer rute) dari Last Seen + pilihan awal di Riwayat Lokasi

## Parent

Spec: [docs/backlog_items/042-tab-tracking-last-seen-riwayat/spec.md](../spec.md)

## What to build

Kolom "Riwayat" (kolom biasa) di tabel Last Seen Sales/Koordinator dengan tombol "Lihat riwayat" yang membuka drawer berisi rute orang itu pada tanggal posisi terakhirnya, dengan tautan ke halaman Riwayat Lokasi penuh (orang dan tanggal sudah terpilih).

## Acceptance criteria

- [x] Baris Last Seen yang punya posisi menampilkan tombol "Lihat riwayat" yang membuka drawer rute (peta Leaflet, tanggal bisa diganti) dengan tautan "Buka halaman Riwayat Lokasi" ke `/tracking_sales_history_location?sales_id=<id>&tanggal=<YYYY-MM-DD>` (koordinator: `/tracking_koordinator_history_location?koordinator_id=…`); baris "Belum ada data" tanpa tombol.
- [x] Tombol hanya tampil bila akun boleh membuka halaman riwayat (slug riwayat ada di `tabHalaman`).
- [x] `TrackingSalesController@index` / `TrackingKoordinatorController@index` mengirim prop `pilihId` dari query (`null` bila tidak ada).
- [x] `History.vue` memilih `pilihId` saat halaman dibuka (walau tanpa titik GPS pada tanggal itu) dan menggulir ke barisnya; setelahnya pilihan otomatis berjalan seperti biasa.

## Implementation steps

- [x] Prop `pilihId` di kedua controller.
- [x] `History.vue`: prop `pilihId`, pilihan awal di `pilihOtomatis`.
- [x] `Menu/Show.vue`: kolom `riwayat` + slot sel `#cell-riwayat` untuk `tracking_last_seen_sales` / `tracking_last_seen_koordinator` + Modal drawer; `Support/rute.js` (diekstrak dari `Tracking/History.vue`), `partials/RuteHarianPeta.vue` + spec.
- [x] Feature test `pilihId` sales & koordinator.

## Blocked by

- 01-tab-penghubung-last-seen-riwayat.md (tujuan dan izin tautan dibaca dari `tabHalaman`)
