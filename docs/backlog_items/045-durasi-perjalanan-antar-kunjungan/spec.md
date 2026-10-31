# Durasi Perjalanan Antar Kunjungan di Drawer Riwayat

## Problem Statement
Daftar kunjungan outlet di drawer Riwayat Lacak Motoris (spec 044) menampilkan berapa lama motoris berada di tiap outlet, tapi tidak menampilkan berapa lama dan sejauh apa motoris berpindah dari satu outlet ke outlet berikutnya. Admin yang ingin menilai efisiensi rute (waktu di jalan vs waktu di outlet) harus menghitung selisih jam checkout dan checkin sendiri.

## Solution
Di antara dua kunjungan berurutan pada daftar, tampilkan baris penghubung kecil: "↓ 12 menit di jalan · 3,4 km".

- **Durasi** = jam checkin kunjungan berikutnya dikurangi jam checkout kunjungan sebelumnya (`trx_kunjungan.checkout_at`). Dibulatkan ke menit; di bawah satu menit tampil "kurang dari 1 menit"; mulai 60 menit tampil "1 j 5 mnt".
- **Jarak** = jarak garis lurus (haversine) antara koordinat kedua kunjungan, dalam meter (di bawah 1 km) atau kilometer satu desimal. Ini perkiraan, bukan jarak jalan. Bila salah satu kunjungan tanpa koordinat, jarak tidak ditampilkan tapi durasi tetap.
- Baris penghubung tidak muncul bila durasi tidak dapat dihitung: kunjungan sebelumnya belum checkout, atau jam checkin berikutnya lebih awal dari checkout sebelumnya (data tumpang tindih).
- Hanya di daftar kunjungan drawer (Sales). Rute GPS, penanda peta, dan halaman Riwayat penuh tidak berubah.

## User Stories
1. Sebagai admin, saya ingin melihat berapa lama motoris di jalan antara dua outlet, supaya saya tahu waktu yang terpakai untuk berpindah.
2. Sebagai admin, saya ingin melihat perkiraan jarak antar outlet di samping durasi itu, supaya durasi yang panjang bisa dibandingkan dengan jaraknya.
3. Sebagai admin, saya tidak ingin angka yang menyesatkan: bila checkout tidak tercatat atau jam tumpang tindih, baris penghubung tidak ditampilkan.

## Implementation Decisions
- **`KunjunganHarian::untuk`** menghitung `perjalanan` untuk tiap kunjungan selain yang pertama: `{ menit: int, jarak_m: ?int }` atau `null`. Waktu dihitung dari `checkout_at` dan `checkin_at` mentah (UTC) agar tidak terpengaruh konversi zona; jarak dari `lat`/`lng` kedua kunjungan dengan rumus haversine (helper privat di kelas yang sama).
- **Endpoint** tidak berubah selain bentuk tiap kunjungan (`perjalanan` baru).
- **Vue**: `RuteHarianPeta.vue` menyisipkan baris `.rute-perjalanan` sebelum kunjungan yang punya `perjalanan`. Format durasi dan jarak lewat helper baru `durasiSingkat(menit)` dan `jarakSingkat(meter)` di `Support/format.js`. Gaya lewat satu kelas di `app.css`.
- Tidak ada perubahan skema atau hak akses.

## Testing Decisions
- Feature test (`KunjunganDrawerRiwayatTest`): `perjalanan` kunjungan pertama `null`; kunjungan yang didahului kunjungan ber-checkout menghitung menit dan jarak (haversine, toleransi); jarak `null` bila koordinat salah satunya kosong; `null` bila kunjungan sebelumnya belum checkout; `null` bila checkin lebih awal dari checkout sebelumnya.
- Vitest: `format.spec.js` untuk `durasiSingkat` dan `jarakSingkat`; `RuteHarianPeta.spec.js` untuk baris penghubung (tampil dengan durasi dan jarak, tanpa jarak, tersembunyi bila `perjalanan` null).

## Out of Scope
- Total waktu di jalan / total jarak sehari.
- Jarak menurut jalan (routing) atau durasi per segmen rute GPS.
- Durasi antar titik GPS di popup rute.
