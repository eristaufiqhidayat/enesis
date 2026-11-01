# Analisis jarak antar kunjungan dan kunci indikasi

## Parent

Spec: [docs/backlog_items/054-indikasi-jarak-dekat-kunjungan/spec.md](../spec.md)

## What to build

Kelas `AnomaliJarakKunjungan::analisis()` yang mencari runtun kunjungan berurutan berjarak dekat (pakai `perjalanan.jarak_m` yang sudah ada) dengan ambang dari `PengaturanDeteksiKunjungan::ambil()`, dan menambah kunci `indikasi_jarak_kunjungan` pada endpoint `locations` Sales.

## Acceptance criteria

- [x] Runtun di bawah ambang (`min_kunjungan_berturutan`) tidak menghasilkan peringatan.
- [x] Tepat di ambang menghasilkan peringatan; satu di bawahnya tidak.
- [x] Kunjungan tanpa koordinat atau tanpa `perjalanan` memutus runtun (dua runtun terpisah dinilai, ambil yang terpanjang).
- [x] Ambang dibaca dari `pengaturan_admin` lewat `PengaturanDeteksiKunjungan::ambil()` (ticket 01); fallback ke config bila kunci belum pernah disimpan.
- [x] `locations` Sales memuat `indikasi_jarak_kunjungan` (null atau objek `{ jumlah, kunjungan_id, outlet_id, dari_jam, sampai_jam, saran }` — `outlet_id` ditambahkan di luar spec awal: daftar outlet unik dalam runtun, dipakai ticket 03 untuk saran per outlet); endpoint Koordinator tidak berubah.

## Implementation steps

- [x] `App\Support\Tracking\AnomaliJarakKunjungan`.
- [x] `TrackingSalesController@locations` menambah kunci baru dari kunjungan yang sudah dihitung.
- [x] Unit test `AnomaliJarakKunjunganTest` (9 kasus); feature test `IndikasiJarakKunjunganDrawerTest` untuk endpoint `locations`, termasuk replikasi kasus nyata (4 outlet berbeda, jarak 0 m) yang memicu permintaan fitur ini.

## Blocked by

- [01-pengaturan-ambang-dan-halaman-konfigurasi.md](01-pengaturan-ambang-dan-halaman-konfigurasi.md)
