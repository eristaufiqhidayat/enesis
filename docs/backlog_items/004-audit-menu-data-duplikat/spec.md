# Audit Menu dengan Data Duplikat

## Problem Statement

Beberapa menu di sidebar menampilkan data yang persis sama dengan menu lain, karena keduanya menunjuk ke tabel Marketama yang sama lewat `config/marketama.php` (key `pages.*.tables`). Sistem sudah punya mekanisme untuk menandai ini secara eksplisit lewat key `pages.*.same_as`, yang kalau terisi memunculkan info alert "Data menu ini sama dengan ..." di `resources/js/Pages/Menu/Show.vue:58-61` (lihat `MenuController::show`, `app/Http/Controllers/MenuController.php:69`).

Hasil audit `config/marketama.php` (dicek terhadap definisi `pages` saat ini) menemukan 15 pasangan menu yang tabelnya sama persis — dan untungnya **semua 15 pasangan ini sudah ditandai `same_as` dengan benar**, tidak ada yang lolos tanpa penanda:

| Tabel yang dipakai bersama | Menu "ringkas" (slug) | Menu "Laporan" (slug) |
| --- | --- | --- |
| `lap_checkin_checkout` | `checkin_depo` (Canvassing) | `lap_checkin_checkout` (Canvassing > Laporan) |
| `lap_barang_canvassing` | `barang_canvassing` (Canvassing) | `lap_barang_canvassing` (Canvassing > Laporan) |
| `lap_pengembalian_barang` | `pengembalian_barang` (Canvassing) | `lap_pengembalian_barang` (Canvassing > Laporan) |
| `lap_checkin_outlet` | `checkin_outlet` (Aktivitas Canvas) | `lap_checkin_outlet` (Aktivitas Canvas > Laporan) |
| `lap_penjualan_barang` | `penjualan_barang` (Aktivitas Canvas) | `lap_penjualan_barang` (Aktivitas Canvas > Laporan) |
| `lap_stok_outlet` | `stok_outlet` (Aktivitas Canvas) | `lap_stok_outlet` (Aktivitas Canvas > Laporan) |
| `lap_stok_kompetitor` | `stok_kompetitor` (Aktivitas Canvas) | `lap_stok_kompetitor` (Aktivitas Canvas > Laporan) |
| `lap_absensi_sales` | `absensi_sales` (Absensi dan Penggajian) | `lap_absensi_sales` (Absensi dan Penggajian > Laporan) |
| `lap_outlet_noo` | `outlet_noo` (Outlet dan Visit) | `lap_outlet_noo` (Outlet dan Visit > Laporan) |
| `lap_outlet_all` | `outlet_all` (Outlet dan Visit) | `lap_outlet_all` (Outlet dan Visit > Laporan) |
| `lap_outlet_ac` | `outlet_ac` (Outlet dan Visit) | `lap_outlet_ac` (Outlet dan Visit > Laporan) |
| `lap_outlet_ec` | `outlet_ec` (Outlet dan Visit) | `lap_outlet_ec` (Outlet dan Visit > Laporan) |
| `lap_outlet_ro` | `outlet_ro` (Outlet dan Visit) | `lap_outlet_ro` (Outlet dan Visit > Laporan) |
| `lap_outlet_oss` | `outlet_oss` (Outlet dan Visit) | `lap_outlet_oss` (Outlet dan Visit > Laporan) |
| `lap_join_visit` | `join_visit` (Outlet dan Visit) | `lap_join_visit` (Outlet dan Visit > Laporan) |

Jadi perilaku saat ini sudah benar secara fungsional: setiap menu "ringkas" (akses cepat dari grup aktivitas) menautkan balik ke menu "Laporan" yang setara, dan menampilkan banner yang menjelaskan hal itu. Yang **belum ada** adalah jaminan otomatis bahwa pola ini akan tetap konsisten ke depannya: `same_as` adalah field manual yang harus diisi developer setiap kali menambah menu baru di `config/marketama.php`. Tidak ada test atau validasi yang menolak config kalau:

- Dua `pages` entry punya `tables` yang identik tapi `same_as` dibiarkan `null` (duplikat data tidak diberi tahu ke user).
- `same_as` menunjuk ke slug yang `tables`-nya ternyata berbeda (banner menyesatkan — bilang "sama" padahal datanya tidak sama).
- `same_as` menunjuk ke slug yang tidak ada di `pages` (link rusak, dan `config("marketama.pages.{$page['same_as']}.title")` di `MenuController.php:69` akan menghasilkan judul `null`).

## Solution

Tambahkan test otomatis (bukan perubahan UI/UX — alert yang sudah ada sudah tepat) yang memvalidasi konsistensi `same_as` di `config/marketama.php` setiap kali config berubah:

1. Setiap pasangan `pages` entry yang `tables`-nya identik (dan bukan array kosong) harus saling menandai `same_as` satu sama lain (simetris).
2. Setiap `same_as` yang terisi harus menunjuk ke slug yang benar-benar ada di `pages`, dan `tables` milik slug tujuan harus identik dengan `tables` milik slug asal.
3. (Tidak mengubah pasangan yang sudah ada — 15 pasangan di atas sudah benar dan dipakai sebagai baseline test.)

Ini murni pekerjaan jaring pengaman (regression guard), bukan perubahan perilaku yang terlihat user.

## User Stories

1. Sebagai developer yang menambah menu baru lewat `config/marketama.php`, saya ingin test otomatis gagal kalau saya menambah menu dengan `tables` yang sama dengan menu lain tapi lupa mengisi `same_as`, supaya user tidak dibiarkan bingung melihat data yang sama tanpa penjelasan.
2. Sebagai developer yang mengisi `same_as`, saya ingin test gagal kalau saya salah ketik slug tujuan atau slug itu `tables`-nya ternyata beda, supaya banner "Data menu ini sama dengan..." tidak pernah menyesatkan.
3. Sebagai reviewer PR, saya ingin ada satu test yang menjamin ke-15 pasangan menu yang sudah diaudit ini tetap konsisten, supaya refactor config di masa depan tidak diam-diam merusak penandaan yang sudah benar.

## Implementation Decisions

- Test baru sebagai Feature/Unit test PHP (`tests/Unit/MarketamaConfigSameAsTest.php` atau nama senada), membaca `config('marketama.pages')` langsung — tidak perlu HTTP request.
- Bangun map `table-signature => [slugs]` dari `pages.*.tables` (urutan tabel dalam array dianggap signifikan — pakai array sebagaimana adanya, bukan di-sort, kecuali ditemukan kasus nyata yang butuh itu).
- Untuk tiap grup berisi >1 slug: assert tiap slug di grup punya `same_as` yang mengarah ke slug lain dalam grup yang sama (minimal satu slug "kanonik" yang menjadi tujuan — ikuti pola yang sudah ada: menu di grup Laporan adalah tujuan, menu ringkas adalah penanda `same_as`).
- Untuk tiap `same_as` yang tidak null: assert target ada di `pages` dan `tables` target identik dengan `tables` asal.
- Tidak mengubah `config/marketama.php` itu sendiri kecuali test menemukan pelanggaran baru (hasil audit saat ini: tidak ada pelanggaran, 15 pasangan sudah benar) — lihat Further Notes kalau audit ulang di masa depan menemukan kasus baru.

## Testing Decisions

- Satu test class, murni terhadap array config (tidak perlu DB/HTTP), cepat dan tidak flaky.
- Test ini harus lulus di state `config/marketama.php` saat ini (baseline 15 pasangan) — kalau tidak lulus di awal, berarti ada pelanggaran yang baru ditemukan dan harus diperbaiki sebagai bagian dari tiket ini, bukan diabaikan.

## Out of Scope

- Mengubah UX banner "Data menu ini sama dengan..." (sudah sesuai, lihat `resources/views` / `Show.vue:58-61`).
- Menghapus atau menggabungkan menu yang terbukti duplikat — kedua entry (menu ringkas di grup aktivitas, menu lengkap di grup Laporan) adalah desain yang disengaja meniru Marketama asli (akses cepat vs laporan lengkap dengan filter), bukan bug yang perlu dihapus.
- Audit menu `summary_all_depo` dan `home` yang `tables`-nya kosong (`[]`) — bukan kasus duplikat data, di luar cakupan ini.
