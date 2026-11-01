# Pindahkan ambang deteksi ke localStorage

## Parent

Spec: [spec.md](../spec.md)

## What to build

Tombol Ambang di header modal Riwayat menyimpan dua angka ke localStorage (bawaan 11 m dan 3 kunjungan); modal Riwayat mengirimnya ke endpoint `locations`; server memakai nilai itu, dengan bawaan config bila tidak ada.

## Acceptance criteria

- [x] Bawaan 11 meter dan 3 kunjungan di klien (`Support/deteksiKunjungan.js`) dan di config server.
- [x] Nilai tidak sah atau localStorage rusak/ditolak jatuh ke bawaan tanpa galat.
- [x] `locations` Sales menerima `jarak_dekat_m` dan `min_kunjungan` dan menolak nilai di luar batas; endpoint Koordinator tidak menerima/mengirimnya.
- [x] Controller, kelas pengaturan, rute, halaman, dan menu Pengaturan Deteksi Kunjungan dihapus; tidak ada migration yang diperlukan.
- [x] Menyimpan ambang di modal memuat ulang data tanggal yang dibuka.

## Blocked by

None
