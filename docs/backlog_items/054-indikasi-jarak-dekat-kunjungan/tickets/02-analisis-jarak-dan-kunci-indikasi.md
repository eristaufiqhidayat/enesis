# Analisis jarak antar kunjungan dan kunci indikasi

## Parent

Spec: [docs/backlog_items/054-indikasi-jarak-dekat-kunjungan/spec.md](../spec.md)

## What to build

Kelas `AnomaliJarakKunjungan::analisis()` yang mencari runtun kunjungan berurutan berjarak dekat (pakai `perjalanan.jarak_m` yang sudah ada) dengan ambang dari `PengaturanDeteksiKunjungan::ambil()`, dan menambah kunci `indikasi_jarak_kunjungan` pada endpoint `locations` Sales.

## Acceptance criteria

- [x] Runtun di bawah ambang (`min_kunjungan_berturutan`) tidak menghasilkan peringatan.
- [x] Tepat di ambang menghasilkan peringatan; satu di bawahnya tidak.
- [x] Kunjungan tanpa koordinat atau tanpa `perjalanan` memutus runtun (dua runtun terpisah dinilai, masing-masing dicek terhadap ambang sendiri-sendiri).
- [x] Ambang dibaca dari `pengaturan_admin` lewat `PengaturanDeteksiKunjungan::ambil()` (ticket 01); fallback ke config bila kunci belum pernah disimpan.
- [x] `locations` Sales memuat `indikasi_jarak_kunjungan` (**daftar** `{ jumlah, kunjungan_id, outlet_id, dari_jam, sampai_jam, saran }[]`, bukan satu objek nullable seperti rencana awal — diperbaiki setelah ditemukan bug nyata: kasus Ade Suherman 22 Okt 2026 punya 2 runtun terpisah dalam sehari, dan versi awal yang hanya mengembalikan runtun terpanjang membuat runtun pertama [4 outlet, jarak 0m] tidak pernah ditandai karena kalah panjang dari runtun kedua [6 kunjungan]; `outlet_id` ditambahkan di luar spec awal: daftar outlet unik dalam runtun, dipakai ticket 03 untuk saran per outlet); endpoint Koordinator tidak berubah.

## Implementation steps

- [x] `App\Support\Tracking\AnomaliJarakKunjungan` — mengembalikan SEMUA runtun yang memenuhi ambang (urut dari terpanjang), bukan hanya satu.
- [x] `TrackingSalesController@locations` menambah kunci baru dari kunjungan yang sudah dihitung.
- [x] Unit test `AnomaliJarakKunjunganTest` (10 kasus, termasuk dua-runtun-terpisah); feature test `IndikasiJarakKunjunganDrawerTest` untuk endpoint `locations`, termasuk replikasi kasus nyata (4 outlet berbeda, jarak 0 m, dan skenario dua runtun dalam sehari) yang memicu permintaan fitur ini.

## Blocked by

- [01-pengaturan-ambang-dan-halaman-konfigurasi.md](01-pengaturan-ambang-dan-halaman-konfigurasi.md)
