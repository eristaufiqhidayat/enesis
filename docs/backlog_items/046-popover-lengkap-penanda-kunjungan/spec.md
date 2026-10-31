# Popover Lengkap Penanda Kunjungan di Drawer Riwayat

## Problem Statement
Popover penanda kunjungan di peta drawer Riwayat (spec 044) hanya menampilkan nama outlet, jam, durasi, dan hasil. Informasi yang sudah dikirim backend, yaitu lencana "Di luar rute" dan "Lokasi palsu", perjalanan dari kunjungan sebelumnya (spec 045), dan jarak motoris ke toko saat checkin hanya terlihat di daftar kiri atau tidak tampil sama sekali. Selain itu alasan tidak beli tampil mentah (`toko_tutup`) dan **kosong untuk seluruh data lama**: kunjungan hasil migrasi menyimpan alasannya di `keterangan_lama` ("Stok Banyak", "Toko Sedang Tutup", dst.), sedangkan `KunjunganHarian` hanya membaca kolom milik HP.

## Solution
1. **Popover memuat semua info yang tersedia**, berurutan: (1) nomor kunjungan sebagai lingkaran berwarna yang sama dengan penanda di peta (hijau = beli, oranye = tidak beli) diikuti nama outlet; (2) jam masuk–keluar dan durasi di outlet; (3) hasil: "Beli" atau "Tidak beli: <alasan>"; (4) lencana "Di luar rute" dan "Lokasi palsu" bila berlaku; (5) "Dari kunjungan sebelumnya: 12 menit · 3,4 km" bila ada; (6) "Jarak ke toko saat checkin: 12 m" bila ada. (7) tombol "Lihat di Google Map" ke koordinat checkin (tab baru), sama dengan tombol di popover titik rute. Koordinat (Lat/Lng) sengaja tidak ditampilkan sebagai teks.
2. **Alasan tidak beli dirapikan di backend** (`KunjunganHarian`), sehingga daftar kiri dan popover sama-sama menerimanya: kode HP diubah ke label dari `config('mobile.alasan_tidak_beli')` ("Toko tutup"; kode tak dikenal tampil apa adanya), teks bebas dari HP ditambahkan setelah label dengan pemisah " — ", dan untuk data lama dipakai `keterangan_lama`.

## User Stories
1. Sebagai admin, saya ingin semua informasi kunjungan terlihat saat mengklik penanda, tanpa mencocokkan dengan daftar kiri.
2. Sebagai admin, saya ingin alasan tidak beli terbaca jelas ("Toko tutup", "Stok Banyak"), termasuk untuk kunjungan dari aplikasi lama.
3. Sebagai admin, saya ingin tahu seberapa jauh motoris dari toko saat checkin, supaya checkin yang jauh dari toko mudah dikenali.

## Implementation Decisions
- **`KunjunganHarian`**: ambil juga `kj.keterangan_lama`; `keterangan` = label kode HP (`config('mobile.alasan_tidak_beli')`, kode tak dikenal dipakai apa adanya) digabung " — " dengan `tidak_beli_keterangan` bila keduanya ada, kalau tidak yang tersedia, dan terakhir `keterangan_lama`. Bentuk respons tidak berubah (kunci `keterangan` yang sama).
- **`popupKunjungan` (`Support/rute.js`)** menyusun baris di atas; lencana memakai kelas `.badge` yang sudah ada, dan baris perjalanan/jarak memakai `durasiSingkat`/`jarakSingkat` dari `Support/format.js`. Semua teks dari data di-escape.
- Daftar kiri tidak berubah selain alasan yang kini terisi dan rapi.

## Testing Decisions
- Feature test (`KunjunganDrawerRiwayatTest`): `keterangan` berisi label ("Toko tutup") untuk kode HP, gabungan label dan teks bebas, `keterangan_lama` untuk data lama, null bila tidak ada.
- Vitest (`rute.spec.js`): popover memuat semua baris saat datanya ada, tidak memuat baris yang datanya kosong (tanpa lencana, perjalanan, atau jarak), dan meng-escape isi.

## Out of Scope
- Nilai transaksi, item yang dibeli, alamat outlet, dan foto kunjungan (datanya belum dikirim backend).
- Mengubah daftar kiri atau perilaku klik baris.
