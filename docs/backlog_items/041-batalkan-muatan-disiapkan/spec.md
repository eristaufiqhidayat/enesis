# Batalkan Muatan Canvassing yang Masih Disiapkan (Soft Delete)

## Problem Statement
Muatan canvassing yang dibuat dari web (spec 039) dan diedit (spec 040) belum bisa dihapus. Kalau muatan dibuat untuk motoris atau tanggal yang salah, satu-satunya jalan adalah mengubah database langsung, padahal selama muatan masih berstatus `disiapkan` aplikasi HP motoris tetap menampilkannya dan motoris bisa menerimanya. Tabel `trx_canvassing` juga belum punya penanda hapus/batal (tidak ada `deleted_at` atau `dibatalkan_at`), sedangkan nomor muatan sebaiknya tidak hilang dari jejak data.

## Solution
Muatan yang masih berstatus `disiapkan` (belum diterima motoris) bisa **dibatalkan** dari drawer detail lewat tombol "Batalkan muatan" dengan konfirmasi. Pembatalan bersifat soft: baris `trx_canvassing` tetap ada, hanya diberi `dibatalkan_at` (mengikuti pola `trx_penjualan.dibatalkan_at`), `dibatalkan_oleh`, dan `status = 'dibatalkan'`. Muatan yang dibatalkan:
- tidak tampil lagi di Daftar Muatan Motoris dan tidak bisa dibuka/diubah lewat web,
- tidak terlihat dan tidak bisa diterima oleh motoris di aplikasi HP,
- tidak dihitung pada aturan "satu muatan per motoris per tanggal", sehingga muatan pengganti bisa dibuat.

Muatan yang sudah diterima atau seterusnya tidak bisa dibatalkan dari web.

## User Stories
1. Sebagai staf depo, saya ingin membatalkan muatan Disiapkan yang salah (motoris/tanggal/isi), supaya motoris tidak menerimanya dan saya bisa membuat muatan pengganti.
2. Sebagai staf depo, saya ingin diminta konfirmasi sebelum muatan dibatalkan, karena setelah dibatalkan muatan hilang dari daftar.
3. Sebagai staf depo, saya ingin tombol "Batalkan muatan" hanya muncul untuk muatan Disiapkan, dan tidak ada untuk muatan yang sudah diterima.
4. Sebagai staf depo, kalau motoris ternyata sudah menerima muatan saat saya akan membatalkannya, saya ingin pembatalan ditolak dengan pesan jelas ("Muatan sudah diterima motoris, tidak bisa dibatalkan") dan drawer menampilkan isi terbaru.
5. Sebagai motoris, saya tidak ingin melihat atau menerima muatan yang sudah dibatalkan di aplikasi HP.
6. Sebagai pengelola data, saya ingin tahu siapa yang membatalkan sebuah muatan dan kapan, langsung dari barisnya di `trx_canvassing`: `dibatalkan_oleh` menyimpan ID akun (`users.id`) pembatalnya dan `dibatalkan_at` waktunya.
7. Sebagai pemilik role, saya ingin hak akses `hapus` pada menu `barang_canvassing` menentukan siapa yang boleh membatalkan, dengan cakupan depo/koordinator yang sama seperti aksi lain.

## Implementation Decisions
- Migration baru: kolom `dibatalkan_at` (timestamp nullable) dan `dibatalkan_oleh` (unsignedBigInteger nullable, `users.id`) pada `trx_canvassing`. Nomor tetap unik dan tidak dipakai ulang.
- Route (grup auth, sebelum catch-all): `DELETE /barang_canvassing/{canvassing}` → `BarangCanvassingController@batalkan`, middleware `hak:barang_canvassing,hapus`, `whereNumber`.
- Cakupan dan guard sama dengan `ubahItem`: muatan diambil lewat `SumberCanvassing::dasar` (Koordinator → Motoris + `Akses::batasiKaryawan`; di luar cakupan → 404), lalu `DB::transaction` + `lockForUpdate`, hanya jika `status = 'disiapkan'` dan `diterima_at IS NULL` dan belum dibatalkan; selain itu 409. Isi: `dibatalkan_at = now()`, `dibatalkan_oleh = user`, `status = 'dibatalkan'`. Item (`trx_canvassing_item`) tidak dihapus.
- `SumberCanvassing::dasar` menambah `whereNull('c.dibatalkan_at')` sehingga daftar muatan, drawer detail (`item`), `ubahItem`, dan `batalkan` otomatis mengabaikan muatan yang sudah dibatalkan (laporan lain sudah hanya memuat yang diterima). `masihDisiapkan()` ikut memastikan status `disiapkan`, jadi muatan dibatalkan tidak bisa diedit.
- Aplikasi HP: `HariKerja::canvassing()` (muatan terbaru hari ini) dan `Mobile\CanvassingController::milik()` (cari berdasarkan nomor) mengabaikan muatan yang dibatalkan. Akibatnya motoris tidak melihatnya, dan menerima nomor yang dibatalkan dijawab "Nomor canvassing tidak ditemukan" (404), bukan diterima.
- Aturan satu muatan per motoris per tanggal di `store` tidak menghitung muatan yang dibatalkan.
- Respons `GET /barang_canvassing/{id}/item` ditambah `dapat_dibatalkan` (status Disiapkan dan akun punya hak `hapus`), seperti `dapat_diubah`.
- Pelaku pembatalan disimpan sebagai ID akun saja: `dibatalkan_oleh` = `id` akun (`users.id`) yang menekan Batalkan, diisi bersama `dibatalkan_at` dalam transaksi yang sama. Nilainya selalu diambil dari akun yang sedang login di server, tidak pernah dari input request. Nama tidak disimpan di tabel muatan (nama bisa dicari lewat `users.id`). Pembatalan tetap dicatat di riwayat admin lewat `Akses::catat(..., 'hapus', "Batalkan muatan {nomor}")` seperti aksi admin lain.
- UI di `DetailMuatanCanvassing.vue`: tombol "Batalkan muatan" (varian `outline-danger`, ikon Lucide) di samping "Ubah item", hanya bila `dapat_dibatalkan` dan tidak sedang mode ubah; klik membuka `Modal` konfirmasi ("Batalkan muatan {nomor}? Muatan tidak tampil lagi di daftar dan tidak bisa diterima motoris."); setelah sukses: toast sukses, drawer menutup, tabel daftar dimuat ulang (`router.reload`). Bila 409: toast galat dan drawer memuat ulang isi terbaru.
- Penghitung "data terkait" saat menghapus karyawan (`KaryawanController`) tetap menghitung muatan yang dibatalkan, karena barisnya masih ada dan merujuk karyawan itu.

## Testing Decisions
- Feature test (`tests/Feature/Admin/`, dasar `AdminTestCase`): batalkan muatan Disiapkan (kolom terisi, `dibatalkan_oleh` sama dengan `id` akun yang login, status `dibatalkan`, item tetap ada; entri riwayat admin tercatat), hilang dari `/barang_canvassing` dan dari drawer (404), tidak bisa diedit; muatan diterima/dikembalikan/selesai/sudah dibatalkan ditolak 409; role tanpa hak `hapus` 403; role bercakupan depo tidak bisa membatalkan muatan depo lain (404); riwayat admin tercatat; muatan pengganti untuk motoris dan tanggal yang sama bisa dibuat setelah pembatalan; `item` memuat `dapat_dibatalkan` sesuai status dan hak.
- Test API mobile: muatan yang dibatalkan tidak muncul sebagai muatan hari ini, dan menerima nomornya menghasilkan 404.
- Assert lewat respons dan efek di tabel, bukan implementasi internal; verifikasi alur konfirmasi di browser.

## Out of Scope
- Membatalkan muatan yang sudah diterima/dikembalikan/selesai (ditangani di aplikasi HP atau alur retur).
- Memulihkan (membatalkan pembatalan) muatan dari web, dan menampilkan/menyaring muatan yang dibatalkan di daftar.
- Alasan pembatalan dan notifikasi ke aplikasi HP.
- Hapus permanen (hard delete) muatan.
