# PRD — Enesis Canvassing (Pembaruan Marketama Canvassing System)

| | |
| --- | --- |
| Versi | 2.2 (draf) |
| Tanggal | 22 Okt 2026 |
| Pemilik produk | Super Admin PT. Marketama |
| Tim pengembang | Rangga Mauliza (menggantikan tim pengembang sebelumnya) |
| Status | Fase 0 (baseline hasil penelusuran) selesai; pembaruan sistem direncanakan |

## 1. Ringkasan

**Enesis Canvassing** adalah pembaruan (rebuild) dari **Marketama Canvassing System**, sistem admin untuk operasi penjualan keliling (canvassing): motoris/sales, koordinator, depo/warehouse, outlet, stok, absensi, dan penggajian. Aplikasi tetap **milik PT. Marketama**. Pengembangannya dialihkan ke tim kami, sedangkan product owner dan pengguna akhir tetap sama. Aplikasi dibangun di atas Laravel 13 (PHP ^8.3).

Tim kami **tidak mendapat akses ke database, kode sumber, atau dokumentasi** aplikasi lama. Satu-satunya sumber informasi adalah apa yang ditampilkan aplikasi existing yang berjalan di internet. Karena itu fondasi proyek ini adalah **hasil penelusuran (crawl) agen Claude terhadap live app**: struktur menu, label kolom, isi tabel, dan tampilan halaman peta. Hasil penelusuran tersimpan sebagai **29 file CSV di `database/seeders/data`** (data yang tersedia dari aplikasi lama) dan menjadi satu-satunya sumber data awal. Repo saat ini adalah *baseline* hasil penelusuran itu: tiruan antarmuka yang read-only dengan data snapshot. CSV tersebut akan dikonversi menjadi seeder database aplikasi baru. PRD ini menetapkan bagaimana baseline dikembangkan menjadi aplikasi pengganti yang diterima product owner.

Aplikasi mobile (sales/koordinator di lapangan) akan **dibuat terpisah** dan berkomunikasi dengan aplikasi ini lewat **API**. Jadi aplikasi ini berperan ganda: admin web dan backend API.

## 2. Latar belakang dan masalah

- Pengembangan aplikasi Marketama beralih ke tim baru. Tim baru harus melanjutkan tanpa serah terima teknis (kode, skema, dokumentasi), hanya dengan akses ke tampilan aplikasi yang berjalan.
- Spesifikasi tertulis tidak ada. Satu-satunya "spesifikasi" adalah perilaku live app, yang hanya bisa diamati.
- Skema database, aturan bisnis, dan kontrak API ke aplikasi mobile tidak diketahui dan harus disimpulkan lalu divalidasi ke product owner.
- Data yang tersedia terbatas pada hasil penelusuran: master dan referensi (29 CSV, ±6 MB) yang aman disimpan di repo. Data transaksi, karyawan, user, outlet, dan GPS (sampai 1,7 juta baris/tabel, memuat data pribadi) tidak ada di repo dan tidak boleh masuk repo.

## 3. Tujuan

1. Mengambil alih pengembangan Marketama Canvassing System dengan kesetaraan fungsi terhadap aplikasi existing, lalu mengembangkannya sesuai arahan product owner.
2. Mempertahankan antarmuka yang familiar (9 grup, 119 menu, ikon dan urutan sama) agar pengguna tidak perlu belajar ulang.
3. Menjadikan hasil penelusuran sebagai baseline yang terverifikasi: setiap menu, kolom, dan data terdokumentasi dan dapat diuji.
4. Menyediakan **API** sebagai alat komunikasi dengan aplikasi mobile yang dikembangkan terpisah.
5. Menambah kemampuan baru yang disetujui product owner: autentikasi dan peran, filter dan ekspor, dashboard.
6. Memungkinkan perubahan cepat lewat konfigurasi, tanpa kode baru untuk menu data biasa.
7. Mengonversi data hasil penelusuran (CSV) menjadi seeder database yang bersih, idempoten, dan teruji, sehingga aplikasi baru dapat dibangun ulang dari nol (`migrate --seed`).
8. Memisahkan data pribadi dari repositori.

### Bukan tujuan (saat ini)

- Aplikasi mobile; dikerjakan terpisah. Aplikasi ini hanya menyediakan API-nya.
- Akses atau migrasi langsung dari database Marketama (tidak tersedia).
- Mengubah proses bisnis tanpa persetujuan product owner.

## 4. Pengguna

| Persona | Kebutuhan |
| --- | --- |
| Super Admin PT. Marketama (product owner) | Menelusuri data semua depo, memeriksa transaksi, absensi, outlet; mengelola user dan master data. |
| Supervisor / Koordinator area | Memantau kinerja motoris, rute kunjungan, dan cakupan area. |
| Staf HR / penggajian | Mengelola absensi, izin, potongan, insentif, dan gaji. |
| Staf warehouse | Mengelola loading barang, retur, setoran, dan stok. |
| Aplikasi mobile (klien API) | Mengirim checkin, absensi, penjualan, GPS; membaca master data dan target. |
| Pengembang / analis data | Mengimpor data, menambah menu dan tabel, membuat laporan turunan. |

## 5. Posisi saat ini (Fase 0: baseline hasil penelusuran)

Sudah ada dan berjalan:

- **Sidebar berbasis konfigurasi** (`config/marketama.php`): 9 grup, 119 menu, submenu bertingkat. Rute catch-all `/{slug}` hanya menerima slug terdaftar.
- **Halaman data generik**: tabel dengan label kolom, pencarian LIKE pada semua kolom terkonfigurasi, paginasi 50 baris per tabel (`page_{tabel}`). Tabel yang belum ada dilewati.
- **Skema**: ±101 tabel Marketama ditambah tabel tracking dan area, lewat migration.
- **Data hasil penelusuran** di `database/seeders/data`: 29 CSV (±6 MB), satu file per tabel. Terbesar: `kelurahan` (84.091 baris), `kecamatan` (7.701), `kota_kabupaten` (516), `provinsi` (38). Sisanya master kecil: produk (21), depo (30), wilayah depo (31), konversi poin per depo (28), hari libur (14), rumus insentif, target, dan kategori. Beberapa file kosong atau hampir kosong (`kategori_surat_peringatan` 0, `panduan` 1, `template_barang_canvassing` 1).
- **Seeder saat ini** (`ReferenceDataSeeder`): memuat CSV apa adanya lewat `CsvTableLoader` (tabel dikosongkan, kolom tak dikenal diabaikan, BOM dibuang). Ini jalur sementara; target akhirnya di §8.
- **Perintah impor eksternal** (`marketama:import <folder> [--only=tabel]`): disiapkan untuk data besar/pribadi di luar repo. Belum ada data semacam itu yang tersedia; status perintah ini (dipertahankan atau dihapus) diputuskan di Fase 1b.
- **Peta Tracking History Koordinator** (`/tracking_koordinator_history_location`): filter tanggal dan koordinator, garis rute, Start/End, titik lokasi beserta alamat, lokasi depo, lapisan Satelit/2D/Garis Jalan (Leaflet; tile OSM dan Esri).
- **Design Polygon Area** (`/master_area/design_area`): ±13.254 polygon area, lokasi depo, pencarian area, gambar dan hapus polygon.
- **Ikon kanan atas**: Tambah Loading Barang, Tambah Barang Canvassing (placeholder *coming soon*), Design Polygon Area, Notifikasi.

Keterbatasan baseline karena sumbernya hasil pengamatan:

- Hanya **apa yang tampak** di layar yang tertangkap: kolom yang ditampilkan, bukan seluruh skema asli; tipe data dan relasi antartabel disimpulkan, belum terverifikasi.
- **Aturan bisnis tidak teramati**: rumus insentif dan potongan hanya tampak sebagai data, bukan logika perhitungan; validasi, status/alur persetujuan, dan hak akses tidak diketahui.
- **Alur tulis hampir seluruhnya belum diamati** (form tambah/ubah/hapus, persetujuan izin, dll.).
- Data adalah **snapshot** (22 Okt 2026), bukan data hidup.
- **Data bernilai tampilan, bukan nilai mentah**: CSV menyimpan teks seperti yang tampil di layar, mis. harga `Rp 25.000`, tanggal `10 Oktober 2026 18:00`, dan penanda `Ya`. Nilai ini perlu dinormalisasi (angka, tanggal ISO, boolean) saat dikonversi menjadi seeder.
- **Cakupan data sempit**: hanya master/referensi yang tersedia. Tabel transaksi, karyawan, user, outlet, tracking, dan area belum punya data di repo; aplikasi baru dimulai dengan data kosong untuk tabel tersebut.

## 6. Lingkup produk target

### 6.1 Kesetaraan fungsi (parity) dengan Marketama

Per grup menu, target akhirnya bukan hanya baca tetapi juga operasi tulis yang ada di Marketama.

| Grup | Fungsi baca (ada) | Fungsi tulis / lanjutan yang ditargetkan |
| --- | --- | --- |
| Utama | Beranda, checkin pagi, sales tidak penjualan/absen, rekap SKU, summary depo, coverage, performance, monitor absensi | Dashboard visual dan ringkasan per depo |
| Canvassing | Checkin/checkout depo, template & barang canvassing, pengembalian, tracking koordinator, laporan | Form Barang Canvassing, tracking sales (last seen dan history) |
| Warehouse | Loading, retur, setoran, setor margin, distributor, laporan | Form Loading Barang, retur, setoran |
| Aktivitas Canvas | Checkin outlet, penjualan, stok outlet/kompetitor, laporan efective call dan barang terbaik | Laporan turunan dan agregasi |
| Absensi dan Penggajian | Absensi, izin, parameter & potongan gaji, penggajian, laporan insentif | Alur persetujuan izin, perhitungan insentif dan gaji |
| Outlet dan Visit | NOO, All, AC, EC, RO, OSS, duplikat/fiktif, Join Visit, laporan | Validasi outlet, penandaan duplikat/fiktif |
| User dan Karyawan | Users per peran, karyawan, surat peringatan, sesi login, laporan | CRUD user dan karyawan, surat peringatan |
| Master Data & Pengaturan | Panduan, pengumuman, produk, area, depo, project, libur, target, wilayah, rumus insentif | CRUD master data, desain area (sudah sebagian) |

### 6.2 Kemampuan baru (melampaui Marketama)

- Autentikasi, peran, dan pembatasan menu/depo/project.
- Filter per kolom, tanggal, dan depo; pengurutan; ekspor CSV/Excel.
- Dashboard grafik: pencapaian SKU, kehadiran, coverage, efective call.
- Pencarian cepat yang efisien untuk tabel jutaan baris.
- Log audit untuk perubahan data sensitif (gaji, user, master).
- **API untuk aplikasi mobile** (REST/JSON, autentikasi token): kontrak didokumentasikan dan diberi versi. Cakupan awal: login, master data, absensi, checkin/checkout depo dan outlet, loading/retur/setoran, penjualan, stok, pengiriman GPS. Kontrak final diselaraskan dengan tim mobile.
- Pembaruan data berkala selama masa transisi (lihat §8).

## 7. Persyaratan non-fungsional

| Aspek | Persyaratan |
| --- | --- |
| Stack | Laravel 13, PHP ^8.3, Vite + Tailwind 4, Leaflet |
| Database | SQLite untuk pengembangan; MySQL/PostgreSQL untuk produksi. Tes memakai SQLite in-memory |
| Kinerja | Halaman tabel < 2 detik pada data penuh (paginasi server-side, indeks pada kolom cari/urut; pertimbangkan pencarian per kolom atau full-text menggantikan LIKE ke semua kolom) |
| Keamanan & privasi | Autentikasi wajib sebelum dipakai di luar lokal; data pribadi tidak masuk repo; hak akses per peran dan depo; kebijakan retensi/penyamaran data |
| Bahasa | Antarmuka dan komentar kode Bahasa Indonesia |
| Kualitas | Laravel Pint; tes fitur per halaman dan per alur tulis |
| Koneksi | Peta butuh internet untuk tile (opsi: penyedia tile sendiri) |

## 8. Strategi data dan migrasi dari Marketama

1. **Baseline (selesai)**: antarmuka dan 29 CSV hasil penelusuran di `database/seeders/data`.
   - **Konversi CSV → seeder** (Fase 1b): setiap CSV diubah menjadi seeder per tabel (satu kelas per domain, mis. Wilayah, Produk, Depo, Insentif) yang:
     - membaca CSV sebagai sumber, dengan normalisasi nilai: `Rp 25.000` → angka (integer rupiah), tanggal Indonesia → `datetime`, `Ya/Tidak` → boolean;
     - idempoten (`upsert` pada kunci alami, bukan truncate), sehingga aman dijalankan ulang;
     - memuat tabel induk sebelum anak (provinsi → kota/kabupaten → kecamatan → kelurahan; kategori/merk/satuan → produk; depo → wilayah depo) dan memakai kunci relasi, bukan teks nama, bila memungkinkan;
     - dikerjakan dalam chunk untuk file besar (`kelurahan`), dengan indeks pada kolom relasi;
     - dites: jumlah baris per tabel sama dengan CSV, contoh nilai ternormalisasi benar, seed dua kali tidak menggandakan data.
   - Data kecil yang stabil (mis. kategori, tipe outlet) dapat dipindah ke array di dalam seeder; data wilayah yang besar tetap dibaca dari CSV.
   - Hanya data referensi yang ikut di seeder; data pribadi tidak.
2. **Verifikasi**: bandingkan setiap menu aplikasi baru dengan live app (jumlah kolom, label, jumlah baris, contoh nilai). Selisih dicatat sebagai defek. Perlu checklist per 119 menu.
3. **Penelusuran lanjutan terarah**: amati alur tulis (form, validasi, status, persetujuan) dan perhitungan (insentif, potongan, gaji) untuk merumuskan aturan bisnis. Tiap aturan yang disimpulkan dicatat sebagai asumsi dan divalidasi dengan Super Admin PT. Marketama sebagai product owner.
4. **Pembaruan data**: bila ada tarikan baru, CSV di `database/seeders/data` diganti dan seeder dijalankan ulang (idempoten); perubahan CSV masuk lewat commit sehingga dapat ditinjau. Untuk data transaksi/pribadi, jalur impor eksternal atau penginputan langsung lewat aplikasi baru dipilih bersama product owner.
5. **Masa paralel**: aplikasi baru dan aplikasi lama berjalan bersamaan; hasil dibandingkan (terutama gaji dan insentif) sebelum cut-over.
6. **Cut-over**: transaksi baru dicatat di aplikasi baru; aplikasi lama dipensiunkan atas keputusan PT. Marketama.

Karena tidak ada akses database, seluruh data awal berasal dari CSV hasil penarikan lewat antarmuka; keselarasan dengan aplikasi mobile (yang kelak memakai API baru) dikoordinasikan dengan tim mobile.

## 9. Risiko dan celah

1. **Aturan bisnis tidak terlihat dari UI**: perhitungan gaji/insentif yang salah berdampak langsung pada karyawan. Mitigasi: validasi paralel dan persetujuan pemilik proses sebelum dipakai.
2. **Skema disimpulkan, bukan diketahui**: kolom tersembunyi, tipe, dan relasi bisa meleset. Mitigasi: verifikasi per menu dan skema yang mudah dimigrasi.
3. **Data penelusuran bisa tidak lengkap atau usang** (snapshot 22 Okt 2026; hanya master/referensi). Mitigasi: bandingkan jumlah baris CSV dengan live app, dan perbarui CSV serta jalankan ulang seeder bila ada tarikan baru.
4. **Tanpa serah terima teknis**: kode, skema, dan kontrak API lama tidak tersedia. Mitigasi: product owner (Super Admin PT. Marketama) menjadi pemutus akhir atas aturan bisnis yang disimpulkan.
5. **Data pribadi** (karyawan, gaji, GPS) tanpa autentikasi saat ini terbuka. Wajib ditutup sebelum dipakai bersama.
6. **Kinerja LIKE `%…%`** pada tabel jutaan baris.
7. **Ketergantungan tile eksternal** untuk peta.
8. **Kontrak API dengan aplikasi mobile**: dikerjakan tim lain dan paralel; perubahan kontrak menghambat keduanya. Mitigasi: dokumentasi API berversi (mis. OpenAPI) dan tes kontrak.
9. **Ketergantungan pada struktur live app**: bila Marketama berubah, hasil penelusuran berikutnya dapat berbeda dari baseline.
10. **Kesalahan normalisasi saat konversi**: nilai tampilan (Rp, tanggal Indonesia, label) dapat salah diparse atau kehilangan presisi. Mitigasi: tes per kolom terkonversi dan pembandingan sampel dengan CSV asli.
11. **Ukuran dan kinerja seeder** untuk `kelurahan` (84 ribu baris) dan kunci relasi berbasis teks. Mitigasi: chunk, transaksi, dan indeks.

## 10. Peta jalan

| Fase | Isi | Status |
| --- | --- | --- |
| 0 | Baseline hasil penelusuran: sidebar 119 menu, tabel + cari + paginasi, migration, seeder, impor CSV, peta tracking koordinator, Design Polygon Area | Selesai |
| 1 | Verifikasi baseline terhadap live app (checklist 119 menu); perbaikan kolom/label/data; pencatatan skema dan asumsi | Rencana |
| 1b | Konversi 29 CSV menjadi seeder database (normalisasi nilai, idempoten, urutan relasi, tes seeder); keputusan nasib `marketama:import` | Rencana |
| 2 | Autentikasi, peran, dan pembatasan menu/depo/project; log audit; fondasi API (autentikasi token, versi, dokumentasi) | Rencana |
| 3 | Filter, pengurutan, ekspor, perbaikan kinerja pencarian dan indeks | Rencana |
| 4 | Dashboard Beranda, Summary All Depo, rekap SKU, coverage dan performance | Rencana |
| 5 | Alur tulis modul Warehouse dan Canvassing: Loading Barang, Barang Canvassing, retur, setoran; tracking sales | Rencana |
| 6 | Modul User/Karyawan dan master data CRUD; Outlet dan Join Visit | Rencana |
| 7 | Absensi, izin, insentif, potongan, dan penggajian (aturan bisnis hasil validasi) | Rencana |
| 7b | API untuk aplikasi mobile per modul (dikerjakan bersamaan dengan fase 5–7, mengikuti kontrak yang disepakati dengan tim mobile) | Rencana |
| 8 | Impor berkala, masa paralel, cut-over | Rencana |

Urutan fase 5–7 dapat ditukar sesuai prioritas bisnis; fase 7 sengaja terakhir karena risiko tertinggi.

## 11. Kriteria penerimaan

**Baseline (Fase 0)** — sudah berlaku:
- Semua slug di `marketama.menu` terdaftar di `marketama.pages` dan halamannya terbuka tanpa error (`MenuTest`).
- Pencarian dan paginasi per tabel bekerja.
- `migrate --seed` pada database kosong berhasil dan memuat data referensi dari CSV; `marketama:import` memuat CSV sesuai aturan.
- Peta tracking dan Design Area berfungsi; `composer test` lulus.

**Seeder (Fase 1b)**:
- Setiap dari 29 CSV punya seeder yang menghasilkan jumlah baris sama dengan CSV (kecuali baris yang dibuang dengan alasan tercatat).
- Kolom harga, tanggal, dan boolean tersimpan dalam tipe asli, bukan teks tampilan.
- `migrate:fresh --seed` berhasil dari database kosong, dan `db:seed` kedua kali tidak menggandakan data.
- Tidak ada data pribadi di `database/seeders`.

**Paritas (Fase 1)**:
- Untuk setiap menu: label kolom dan jumlah baris sama dengan live app pada tanggal tarikan, atau selisih tercatat dan dijelaskan.

**Pengganti aplikasi existing (Fase 8)**:
- Setiap fungsi tulis di aplikasi existing yang dipakai tersedia dan lulus uji penerimaan oleh product owner.
- Endpoint API terdokumentasi, berversi, dan dapat dipakai aplikasi mobile untuk alur yang disepakati.
- Hasil gaji dan insentif selama masa paralel identik dengan Marketama (toleransi disepakati) untuk minimal satu periode penuh.
- Akses dibatasi per peran dan depo; perubahan data sensitif tercatat.

## 12. Metrik keberhasilan

- 119/119 menu terverifikasi terhadap live app.
- Selisih jumlah baris database vs CSV sumber = 0 setelah seeding (29/29 tabel).
- Waktu muat halaman tabel < 2 detik pada data penuh.
- Selisih hasil gaji/insentif selama masa paralel = 0.
- Persentase fungsi aplikasi existing yang telah tersedia (parity).
- Persentase endpoint API yang disepakati dengan tim mobile dan lulus tes kontrak.

## 13. Keputusan dan pertanyaan terbuka

**Sudah diputuskan**
- Akses: tidak ada akses ke database sumber; informasi hanya dari tampilan aplikasi existing.
- Data tersedia: 29 CSV di `database/seeders/data` hasil penelusuran; akan dikonversi menjadi seeder database.
- Aplikasi mobile diperbarui terpisah, memakai API dari aplikasi ini.
- Pemilik proses/validasi aturan penggajian dan insentif: Super Admin PT. Marketama.
- Kepemilikan aplikasi tetap PT. Marketama; product owner dan end user tidak berubah, hanya tim developer yang diganti.

**Masih terbuka**
- Apakah ada dokumentasi API aplikasi mobile saat ini atau trafik yang dapat diamati, sebagai acuan kontrak API baru? Atau kontrak dirancang baru bersama tim mobile?
- Apakah rilis pertama meniru aplikasi existing apa adanya, atau ada perubahan proses bisnis yang diminta product owner?
- Apakah data transaksi, karyawan, user, dan outlet akan tersedia (dari mana, dalam bentuk apa), atau aplikasi baru dimulai kosong untuk tabel tersebut?
- Apakah CSV dengan nilai tampilan (harga, tanggal) dianggap cukup, atau perlu ditarik ulang dalam format mentah?
- Frekuensi pembaruan data selama masa paralel, dan target tanggal cut-over.
- Kebijakan retensi dan penyamaran data pribadi (karyawan, GPS, gaji).
- Mekanisme akses Super Admin untuk memvalidasi hasil (jadwal sesi review, kanal konfirmasi).
