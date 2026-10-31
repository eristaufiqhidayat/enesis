# Edit Item Muatan Canvassing yang Masih Disiapkan

## Problem Statement
Muatan canvassing yang dibuat dari web (spec 039) langsung berstatus `disiapkan`, tetapi isinya tidak bisa diubah. Kalau staf depo salah memilih produk atau mengisi qty, satu-satunya jalan sekarang adalah mengubah data langsung di database, atau menunggu motoris menerima muatan yang salah. Edit/hapus muatan sengaja dikeluarkan dari spec 039 (Out of Scope), dan sekarang dibutuhkan.

## Solution
Selama sebuah muatan masih berstatus `disiapkan` (belum diterima motoris di aplikasi HP), daftar itemnya bisa diubah dari drawer detail muatan di Daftar Muatan Motoris: ubah qty, hapus produk, dan tambah produk baru. Perubahan disimpan sekaligus (satu daftar item baru menggantikan yang lama) lewat tombol Simpan. Begitu muatan diterima motoris (`diterima` dan seterusnya), drawer kembali hanya-baca dan server menolak perubahan.

Hanya item yang bisa diubah. Tanggal, depo, dan motoris penerima tetap seperti saat dibuat (mengubahnya = membuat muatan baru).

## User Stories
1. Sebagai staf depo, saya ingin membuka drawer detail muatan berstatus Disiapkan dan menekan "Ubah item", supaya saya bisa memperbaiki isi muatan sebelum motoris menerimanya.
2. Sebagai staf depo, saya ingin mengubah qty bawa, menghapus produk, dan menambah produk lain dalam satu sesi edit, lalu menyimpannya sekaligus.
3. Sebagai staf depo, saya ingin sistem menolak simpan bila daftar kosong, ada produk dobel, atau qty ≤ 0, dengan pesan di baris terkait (aturan yang sama dengan form tambah).
4. Sebagai staf depo, saya ingin tombol "Ubah item" hanya muncul untuk muatan Disiapkan, dan tidak ada untuk muatan yang sudah diterima atau dikembalikan.
5. Sebagai staf depo, kalau motoris ternyata sudah menerima muatan saat saya sedang mengedit, saya ingin simpan ditolak dengan pesan jelas ("Muatan sudah diterima motoris, tidak bisa diubah") dan drawer memuat ulang isi terbaru, bukan menimpa data yang sudah diterima.
6. Sebagai pemilik role, saya ingin hak akses `ubah` pada menu `barang_canvassing` menentukan siapa yang boleh mengedit, dan role bercakupan depo/koordinator hanya bisa mengedit muatan dalam cakupannya.

## Implementation Decisions
- Route baru (grup auth, sebelum catch-all): `PUT /barang_canvassing/{canvassing}/item` → `BarangCanvassingController@ubahItem`, middleware `hak:barang_canvassing,ubah`. Body: `items[]` berisi `produk_id` dan `qty_bawa` (daftar lengkap pengganti).
- Validasi item sama dengan `store`: minimal satu, `produk_id` ada di `mk_produk` dan unik, `qty_bawa` numerik > 0.
- Cakupan: muatan diambil lewat `SumberCanvassing::dasar` dengan `termasukBelumDiterima()` (aturan Koordinator → Motoris + `Akses::batasiKaryawan`, sama dengan endpoint `item`); di luar cakupan → 404.
- Guard status di server: seluruh proses dalam `DB::transaction` dengan `lockForUpdate()` pada baris `trx_canvassing`; hanya jika `status = 'disiapkan'` dan `diterima_at IS NULL`. Selain itu 409 dengan pesan di atas. Penguncian ini mencegah balapan dengan `Mobile\CanvassingController@terima`.
- Penyimpanan berbasis selisih, bukan hapus-semua-lalu-isi-ulang: produk yang masih ada diubah qty-nya, produk baru di-insert, produk yang dihilangkan dihapus dari `trx_canvassing_item` (aman karena `qty_diterima`/`qty_kembali` masih kosong untuk muatan Disiapkan). Header `trx_canvassing` tidak berubah.
- Catat ke riwayat admin (`Akses::catat(..., 'ubah', "Item muatan {nomor}")`), seperti aksi ubah lain.
- Respons `GET /barang_canvassing/{id}/item` ditambah penanda `dapat_diubah` (status masih Disiapkan dan akun punya hak `ubah`) supaya drawer tidak menebak dari teks status.
- UI di `DetailMuatanCanvassing.vue`: tombol "Ubah item" (hanya bila `dapat_diubah`) mengubah drawer ke mode edit — tabel item dengan input qty, tombol Hapus per baris, baris isian produk + qty + "+ Tambah" (pola yang sama dengan langkah 2 wizard: Combobox produk, produk yang sudah ada tidak ditawarkan lagi), serta tombol Simpan dan Batal. Daftar produk memakai `GET /barang_canvassing/opsi?depo=<depo muatan>` (sudah ada). Setelah simpan sukses: toast sukses, drawer kembali ke mode lihat dengan data terbaru, dan baris di tabel daftar ikut diperbarui (`router.reload` pada props tabel).
- Tidak ada migration baru.

## Testing Decisions
- Feature test (`tests/Feature/Admin/`, dasar `AdminTestCase`): ubah qty, hapus produk, tambah produk pada muatan Disiapkan; validasi menolak daftar kosong, produk dobel, qty ≤ 0; muatan berstatus diterima/dikembalikan ditolak (409) dan datanya tidak berubah; role tanpa hak `ubah` 403; role bercakupan depo tidak bisa mengedit muatan depo lain (404); riwayat admin tercatat.
- Respons `item` memuat `dapat_diubah` benar/salah sesuai status dan hak.
- Vitest untuk logika mode edit di drawer bila ada logika murni yang bisa dipisah (daftar produk tersisa, validasi qty); selebihnya diverifikasi manual di browser.
- Assert lewat respons dan efek di tabel, bukan implementasi internal.

## Out of Scope
- Mengubah tanggal, depo, atau motoris penerima muatan.
- Menghapus/membatalkan seluruh muatan.
- Mengedit muatan yang sudah diterima atau dikembalikan (qty diterima/kembali dikelola di aplikasi HP).
- Riwayat perubahan per item (cukup catatan di riwayat admin).
- Notifikasi ke aplikasi HP bahwa isi muatan berubah (motoris membaca isi terbaru saat menerima).
