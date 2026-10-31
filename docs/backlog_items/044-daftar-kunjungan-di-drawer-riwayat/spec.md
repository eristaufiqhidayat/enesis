# Daftar Kunjungan Outlet di Drawer Riwayat Lacak Motoris

## Problem Statement
Drawer Riwayat (tombol "Lihat riwayat" di tabel Lacak Motoris, spec 042) hanya menampilkan rute GPS motoris: garis dan titik-titik koordinat. Admin yang mengecek "motoris ini hari ini mampir ke mana saja?" tidak bisa melihat outlet yang dikunjungi, jam, dan hasilnya (beli / tidak beli) tanpa pindah ke menu laporan lain. Drawer juga terlalu sempit untuk menampung informasi tambahan di samping peta.

## Solution
Perlebar drawer (Modal `size="xl"`) dan bagi isinya dua kolom: **daftar kunjungan di kiri**, peta di kanan.

- Daftar memuat kunjungan outlet motoris pada tanggal yang dipilih, urut menurut jam checkin: nomor urut, nama outlet, jam masuk–keluar, durasi, dan lencana hasil ("Beli" / "Tidak beli"), "Di luar rute", "Lokasi palsu".
- Setiap kunjungan yang punya koordinat juga menjadi penanda bernomor di peta (hijau = beli, oranye = tidak beli). Mengklik baris di daftar memusatkan peta ke penandanya dan membuka popup-nya.
- Mengganti tanggal di drawer memuat ulang rute dan daftar sekaligus.
- Hanya untuk Sales: drawer Koordinator tetap satu kolom (koordinator tidak punya kunjungan outlet di `trx_kunjungan`).
- Peta rute dan halaman Riwayat Lokasi penuh tidak berubah.

## User Stories
1. Sebagai admin, saya ingin melihat daftar outlet yang dikunjungi motoris pada suatu hari di samping petanya, supaya saya tahu rutenya melewati outlet mana.
2. Sebagai admin, saya ingin tahu hasil tiap kunjungan (beli / tidak beli) dan berapa lama motoris di outlet, supaya kunjungan singkat atau tanpa transaksi mudah ditemukan.
3. Sebagai admin, saya ingin klik outlet di daftar dan peta langsung menunjukkan lokasinya.
4. Sebagai admin, saya ingin penanda "di luar rute" dan "lokasi palsu" tampil di daftar, supaya kunjungan mencurigakan terlihat.
5. Sebagai admin dengan cakupan terbatas (depo / downline / diri), saya tidak ingin melihat kunjungan motoris di luar cakupan saya.
6. Sebagai admin, saya ingin drawer yang cukup lebar untuk daftar dan peta berdampingan.

## Implementation Decisions
- **Sumber data**: `trx_kunjungan` (checkin outlet HP dan riwayat lama hasil migrasi, kolom `tanggal` WIB, `checkin_at`/`checkout_at` UTC, `lat`/`lng`, `durasi_detik`, `di_luar_rute`, `mock_location`, `tidak_beli_kategori`/`tidak_beli_keterangan`), nama outlet dari `mk_outlet.nama` atau, untuk outlet baru, `trx_outlet_baru.nama`; hasil beli = ada `trx_penjualan` aktif (`dibatalkan_at IS NULL`) untuk `kunjungan_id` itu.
- **Kelas baru** `App\Support\Tracking\KunjunganHarian::untuk(int $karyawanId, string $tanggal): Collection` (di samping `TitikGps`), memetakan baris ke `{id, outlet, jam, keluar, durasi_menit, lat, lng, jarak_m, di_luar_rute, lokasi_palsu, beli, keterangan}` dengan jam dalam zona aplikasi (`JamLokal`).
- **Endpoint**: respons `GET /tracking_sales_history_location/locations` bertambah `kunjungan` (array). Kunjungan hanya diisi bila motoris ada dalam cakupan role akun (`Akses::batasiKaryawan` pada `mk_karyawan`); di luar cakupan `kunjungan` = `[]`. Endpoint koordinator tidak menambah kunci `kunjungan`; drawer menyembunyikan kolom daftar bila kunci itu tidak ada.
- **Vue**: `RuteHarianPeta.vue` mendapat layout dua kolom (`.rute-harian` di `app.css`, satu kolom di layar sempit), daftar kunjungan, layer penanda bernomor lewat `gambarKunjungan` di `Support/rute.js`, dan batas peta yang mencakup rute dan kunjungan. Modal drawer di `Menu/Show.vue` memakai `size="xl"`.
- Tidak ada perubahan skema atau hak akses baru: izin mengikuti menu Riwayat Lokasi yang sudah ada.

## Testing Decisions
- Feature test (`tests/Feature/TrackingSalesTest`): respons `locations` memuat `kunjungan` urut jam dengan nama outlet (outlet master dan outlet baru), jam WIB, durasi menit, `beli` benar (dengan dan tanpa penjualan, penjualan dibatalkan tidak dihitung), kunjungan tanggal lain tidak ikut; di luar cakupan role → `kunjungan` kosong; endpoint koordinator tanpa kunci `kunjungan`.
- Vitest `RuteHarianPeta.spec.js`: daftar tampil bila `kunjungan` ada, tersembunyi bila tidak; klik baris memusatkan peta; pesan bila tanpa kunjungan.
- Verifikasi manual di browser: lebar drawer, penanda bernomor, klik baris.

## Out of Scope
- Daftar kunjungan di halaman Riwayat Lokasi penuh.
- Kunjungan koordinator (join visit).
- Detail transaksi atau foto kunjungan dari drawer.
