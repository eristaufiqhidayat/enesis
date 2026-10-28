# CRUD Master Produk (Produk, Produk Kompetitor, Kategori, Brand, Satuan)

## Problem Statement
Menu Produk, Produk Kompetitor, Kategori Produk, Brand, dan Satuan Produk hanya menampilkan salinan daftar Marketama (tabel `produk`, `produk_kompetitor`, `kategori_produk`, `merk_produk`, `satuan_produk`). Isinya teks tanpa ID asli dan tanpa relasi, dan tidak bisa diubah. Aplikasi HP memakai `mk_produk`, tetapi merk/kategori/satuan, produk fokus, dan maks poin masih dibaca dari JSON `data` hasil tarikan. Kolom packsize, drop size, keterangan, harga spreading, HET, harga pokok, dan foto belum ada. Produk kompetitor di HP memakai ID lokal yang berbeda dengan Marketama.

## Solution
- **Tabel** (migration `2026_10_28_000001`), ID = ID asli Marketama; data baru memakai ID terbesar + 1:
  - `mk_satuan_produk(id, nama unik)`, `mk_kategori_produk(id, kode unik, nama)`, `mk_merk_produk(id, kode unik, nama, aktif)`, `mk_produk_kompetitor(id, nama unik, satuan_id, foto)`.
  - `mk_produk` + `packsize`, `produk_fokus`, `drop_size`, `maks_poin_per_invoice`, `harga_spreading`, `het`, `harga_pokok`, `keterangan`, `foto`, `foto_lama`; index `merk_id`/`kategori_id`/`satuan_id`.
  - Semua tabel punya `diubah_oleh` + `diubah_pada`.
- **Seeder** `MasterProdukSeeder` (juga dipanggil `DatabaseSeeder`) dari `database/seeders/data/mk_*.csv`: data Marketama 28 Okt 2026 (5 satuan, 2 kategori, 7 brand, 2 produk kompetitor, 21 produk), ditarik dari form Edit tiap produk. Data produk diverifikasi dengan checksum terhadap browser. Aman diulang: baris yang sudah diubah admin sesudah tanggal data tidak ditimpa. Di server: `php artisan migrate && php artisan db:seed --class=MasterProdukSeeder`.
- **Modul CRUD** `MasterProdukController` + definisi `App\Support\Master\MasterProduk`, halaman `MasterProduk/Index.vue` dan `MasterProduk/Form.vue` (satu pasang halaman untuk 5 menu). Route per menu:
  - `GET /{menu}` daftar dengan pencarian;
  - `GET /{menu}/tambah`, `POST /{menu}`, `GET /{menu}/{id}/edit`, `PUT /{menu}/{id}`, `DELETE /{menu}/{id}`;
  - `GET /{menu}/ekspor` CSV, `GET /{menu}/{id}/foto`.
  - Hak per aksi: lihat / tambah / ubah / hapus / ekspor.
- **Aturan**:
  - Kode/nama unik.
  - Brand tidak aktif tidak bisa dipilih untuk produk lain, tapi produk yang sudah memakainya tetap bisa disimpan.
  - Hapus ditolak bila masih dipakai: satuan/kategori/brand oleh produk (satuan juga oleh produk kompetitor); produk oleh `trx_penjualan_item`, `trx_canvassing_item`, `trx_stok_outlet`, atau riwayat `lap_*`.
  - Harga boleh diketik dengan titik ribuan.
  - Foto .jpg/.png maks 2 MB di disk `local` (`master-produk/{menu}/`). Foto lama tetap berupa path Marketama di `foto_lama`.
  - Setiap perubahan tercatat per kolom di `riwayat_admin` dan ditampilkan di form.
- **Aplikasi HP** (`GET /api/mobile/v1/master`):
  - brand/category/unit dari tabel master (cadangan: JSON `data`);
  - `is_focus`/`max_points_per_invoice` dari kolom baru (cadangan: JSON);
  - `since` juga memperhitungkan `diubah_pada` produk dan master merk/kategori/satuannya;
  - `competitor_products` dari `mk_produk_kompetitor` (ID asli Marketama).
- **marketama:tarik**: detail produk ikut mengisi packsize, produk fokus, drop size, maks poin, keterangan, harga spreading, dan HET. Harga pokok tidak ditarik karena form Marketama memakai nama field `cost` untuk Harga Beli dan Harga Pokok.
- Menu Produk, Kategori Produk, Brand, dan Satuan Produk masuk `menu_motoris` (bintang hilang).

## Temuan data Marketama
- Harga Pokok selalu tertimpa Harga Beli (field `cost` ganda di form).
- Brand Plossa tidak aktif tapi dipakai 6 produk. Brand id 39 "Plossa mini starterpack" berkode sama dengan kode produknya.
- Harga spreading/HET tidak wajar: Kispray Hanger Amoris/Violet 438.000, Soffell Jumbo Geranium 833.000, Soffell Bengkoang 833. Drop size Kispray Hanger (Amoris) 1000. Plossa Sachet Citrus tanpa drop size dan HET 0.
- Nama kembar "Plossa Sachet Panasin" (kode `1000561` dan `plosascpns`).

## Testing
`tests/Feature/Admin/MasterProdukTest.php`:
- seeder (ID asli, diulang tidak menimpa perubahan admin);
- daftar & pencarian 5 menu;
- tambah/ubah/hapus satuan + riwayat;
- produk dengan foto, kode kembar, brand tidak aktif;
- hapus produk yang sudah dipakai transaksi ditolak;
- hak per aksi, ekspor CSV;
- API HP (nama master, produk fokus dari kolom, `since` ikut perubahan brand, ID produk kompetitor).

`MenuFilterAngkaTest` dipindah ke menu Kategori User karena Kategori Produk kini modul sendiri.
