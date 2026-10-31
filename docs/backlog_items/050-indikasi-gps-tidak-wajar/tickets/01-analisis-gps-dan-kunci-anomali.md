# Analisis GPS tidak wajar dan kunci anomali_gps di endpoint locations

## Parent

Spec: [docs/backlog_items/050-indikasi-gps-tidak-wajar/spec.md](../spec.md)

## What to build

`AnomaliGps` yang menganalisis titik GPS satu motoris pada satu tanggal (lompatan mustahil, kembali ke asal, penanda mock dari HP), ambangnya di config, dan kunci `anomali_gps` pada respons `locations` Sales.

## Acceptance criteria

- [x] Lompatan = jarak ≥ 3 km dan kecepatan > 120 km/jam antara dua titik berurutan; kembali ke asal = titik berikutnya < 0,5 km dari titik sebelum lompatan.
- [x] `tingkat` = `terdeteksi` bila ada titik GPS atau kunjungan mock dari HP; `indikasi` bila ≥ 5 lompatan dan ≥ 3 kembali ke asal; selain itu `anomali_gps` null.
- [x] Respons memuat jumlah lompatan, kembali ke asal, titik mock, kunjungan mock, lompatan terjauh, dan daftar segmen (maks. 100) untuk digambar.
- [x] Analisis hanya memakai titik tanggal yang diminta.
- [x] Endpoint Koordinator tidak berubah.

## Implementation steps

- [x] Config `marketama.anomali_gps`.
- [x] `AnomaliGps` + `TitikGps::untuk` menyertakan `mock`.
- [x] `TrackingSalesController@locations`.
- [x] Unit test `AnomaliGpsTest` dan feature test.

## Blocked by

None
