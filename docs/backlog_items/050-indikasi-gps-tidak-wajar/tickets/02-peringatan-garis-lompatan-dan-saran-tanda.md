# Peringatan di drawer, garis lompatan di peta, dan saran tanda

## Parent

Spec: [docs/backlog_items/050-indikasi-gps-tidak-wajar/spec.md](../spec.md)

## What to build

Banner peringatan "Indikasi" / "Terdeteksi" di drawer Riwayat (Sales), garis merah putus-putus untuk lompatan di peta, dan isian otomatis form Tandai motoris.

## Acceptance criteria

- [x] Banner kuning (indikasi) atau merah (terdeteksi) memuat ringkasan dan catatan bahwa itu bukan bukti; tidak tampil bila `anomali_gps` null.
- [x] Lompatan digambar sebagai garis merah putus-putus dengan popover jarak, selisih waktu, kecepatan, dan jam; dikosongkan saat tanggal diganti.
- [x] Form Tandai motoris terisi kategori "Lokasi / GPS palsu" dan catatan ringkasan saat dibuka bila ada peringatan, tetap bisa diubah.
- [x] Drawer Koordinator tidak berubah.

## Implementation steps

- [x] `Support/rute.js`: `gambarLompatan`; `RuteHarianPeta.vue`: banner, layer, prop `saran`.
- [x] `TandaMotoris.vue`: prop `saran`, isi form saat dibuka.
- [x] Vitest.

## Blocked by

- 01-analisis-gps-dan-kunci-anomali.md
