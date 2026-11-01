# Ambang deteksi kunjungan disimpan di localStorage

## Latar belakang

Spec 054 menyimpan ambang "kunjungan berturutan berjarak dekat" (jarak dekat dan minimum kunjungan berturutan) di tabel `pengaturan_admin` lewat halaman Pengaturan Deteksi Kunjungan, dengan bawaan 5 meter dan 4 kunjungan. Ambang ini hanya parameter tampilan analisis, tidak perlu jadi data bersama yang diaudit, dan menambah controller, kelas pengaturan, serta catatan `riwayat_admin` yang tidak sebanding manfaatnya.

## Perubahan

- Ambang disimpan di **localStorage browser** (`enesis.deteksiKunjungan`), per pengguna dan per perangkat. Bawaan: **jarak dekat 11 meter**, **minimum kunjungan berturutan 3**.
- Pengaturannya dilakukan **di modal Riwayat**: tombol "Ambang: 11 m · 3 kunjungan" di header (hanya untuk Sales) menampilkan ambang yang berlaku dan membuka form kecil (flyover) berisi dua angka, Simpan, dan "Kembalikan ke bawaan". Menyimpan memuat ulang data tanggal yang sedang dibuka dengan ambang baru. Komponen: `Pages/Menu/partials/PengaturanDeteksiMotoris.vue`.
- Halaman terpisah Pengaturan Deteksi Kunjungan (`/deteksi_kunjungan`), entri menunya, dan hak `deteksi_kunjungan` dihapus; tidak ada controller, endpoint simpan, maupun catatan riwayat admin.
- Modal Riwayat membaca ambang dari localStorage dan mengirimnya ke endpoint `locations` Sales sebagai `jarak_dekat_m` dan `min_kunjungan` (opsional, divalidasi: 1–10000 dan 2–100). Tanpa parameter, server memakai bawaan di `config('marketama.deteksi_jarak_kunjungan')` (11 dan 3). Endpoint Koordinator tidak berubah.
- Dihapus: `PengaturanDeteksiKunjungan` (kelas baca/tulis `pengaturan_admin`), `PengaturanDeteksiKunjunganController`, rute `/deteksi_kunjungan`, halaman `Pengaturan/DeteksiKunjungan.vue`, dan entri menu Pengaturan Deteksi Kunjungan.

## Catatan migrasi

Tidak ada file migration untuk pengaturan ini (spec 054 memakai tabel `pengaturan_admin` yang sudah ada dan fallback config), jadi tidak ada migration yang dibatalkan. Baris `deteksi_kunjungan.*` yang mungkin sudah tersimpan di `pengaturan_admin` pada suatu database kini diabaikan; boleh dihapus manual.

## Konsekuensi yang diterima

- Pengaturan tidak dibagikan antar pengguna atau perangkat; tiap koordinator/supervisor punya ambangnya sendiri.
- Hak `deteksi_kunjungan` tidak ada lagi; siapa pun yang boleh membuka modal Riwayat Sales bisa mengatur ambangnya di browsernya sendiri.
- Angka bawaan ada di dua tempat (config PHP dan `Support/deteksiKunjungan.js`) dan harus sama.
