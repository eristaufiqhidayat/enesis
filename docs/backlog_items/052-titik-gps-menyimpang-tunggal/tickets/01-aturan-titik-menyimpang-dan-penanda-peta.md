# Aturan titik menyimpang tunggal: deteksi, banner, dan penanda peta

## Parent

Spec: [docs/backlog_items/052-titik-gps-menyimpang-tunggal/spec.md](../spec.md)

## What to build

Aturan kedua di `AnomaliGps` (titik ≥ 3 km dari kedua tetangganya yang saling berdekatan, ≥ 3 titik per tanggal), kalimat tambahan di banner, dan penanda titik menyimpang di peta drawer.

## Acceptance criteria

- [x] Titik menyimpang tunggal = jarak ≥ 3 km ke titik sebelum dan sesudah, tetangga saling < 1 km; tidak bergantung pada kecepatan atau jeda waktu.
- [x] `tingkat` `indikasi` bila aturan lompatan lama atau ≥ 3 titik menyimpang; `terdeteksi` (mock HP) tetap lebih tinggi; di bawah ambang `anomali_gps` null.
- [x] Respons memuat `menyimpang`, `menyimpang_terjauh`, dan `titik_menyimpang` (maks. 100, urut waktu).
- [x] Banner menambah kalimat titik menyimpang; peta menggambar lingkaran merah berpusat putih dengan popover; catatan isian form Tandai motoris ikut memuatnya.
- [x] Dhony Rizky Prasetya pada 22 Okt 2026 menjadi Indikasi (3 titik: 14:09, 14:33, 15:00).

## Implementation steps

- [x] Config `marketama.anomali_gps` dan `AnomaliGps::analisis`.
- [x] `Support/rute.js` (`gambarLompatan` + titik menyimpang) dan `RuteHarianPeta.vue`.
- [x] Unit, feature, dan Vitest.

## Blocked by

None
