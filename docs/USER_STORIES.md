# User Story - Aplikasi Marketama

Oct 28, 2026 · Disusun oleh @GAD

## Pendahuluan

Marketama adalah aplikasi manajemen tenaga penjual lapangan (field force management) untuk operasi distribusi/sales multi-depo, mencakup pencatatan outlet, aktivitas canvassing/penjualan, absensi dan penggajian, pelacakan lokasi, serta pelaporan kinerja. Dokumen ini merangkum kebutuhan fungsional aplikasi dalam format user story, disusun berdasarkan modul yang tersedia di menu navigasi aplikasi (observasi langsung pada tanggal 28 Oktober 2026, halaman Laporan Outlet NOO).

**Aktor/Peran Pengguna**

| Peran | Deskripsi Singkat |
| --- | --- |
| Sales / Motoris | Petugas lapangan yang melakukan kunjungan outlet, penjualan, dan pencatatan stok |
| Koordinator | Mengawasi beberapa sales/motoris di suatu area, melakukan join visit |
| SSK / Supervisor | Mengawasi koordinator dan sales, memantau absensi dan kinerja tim |
| Admin / Manajemen | Mengelola master data, konfigurasi, pengguna, dan meninjau laporan menyeluruh |

Setiap user story ditulis dengan format: *"Sebagai \[peran\], saya ingin \[aksi\], agar \[tujuan\]"*, dilengkapi kriteria penerimaan (acceptance criteria) utama.

## Modul Absensi & Penggajian

| Kode | Peran | User Story | Kriteria Penerimaan |
| --- | --- | --- | --- |
| US-01 | Sales | Sebagai sales, saya ingin melakukan check-in absen pagi dengan foto dan lokasi GPS, agar kehadiran saya tercatat akurat sebelum mulai kunjungan | Sistem merekam waktu, koordinat, dan foto; absen ditolak jika di luar radius area kerja |
| US-02 | SSK / Supervisor | Sebagai supervisor, saya ingin melihat daftar sales yang terlambat absen pagi, agar saya bisa menindaklanjuti kedisiplinan tim | Daftar "Sales Tidak Absen Pagi" dan "Absensi Sales Terlambat" tersedia harian dengan filter area/koordinator |
| US-03 | Koordinator / SSK | Sebagai koordinator atau supervisor, saya ingin absensi saya sendiri tercatat terpisah dari sales, agar jenjang pengawasan tetap terpantau | Menu Absensi Koordinator dan Absensi SSK/Supervisor berdiri sendiri dengan riwayat masing-masing |
| US-04 | Sales | Sebagai sales, saya ingin mengajukan permintaan izin tidak masuk atau cuti, agar ketidakhadiran saya tercatat resmi dan disetujui atasan | Form pengajuan izin dengan alasan dan tanggal; status pending/disetujui/ditolak oleh atasan |
| US-05 | Admin / Manajemen | Sebagai admin, saya ingin memproses penggajian berdasarkan data absensi dan kinerja, agar perhitungan gaji karyawan akurat dan tepat waktu | Modul Penggajian menarik data absensi otomatis dan menghasilkan slip gaji per periode |
| US-06 | SSK / Supervisor / Admin | Sebagai supervisor atau admin, saya ingin mengakses laporan rekap absensi dan izin, agar saya dapat mengevaluasi kehadiran tim secara berkala | Laporan dapat difilter per tanggal, area, koordinator, dan diekspor ke Excel |

## Modul Outlet & Visit

| Kode | Peran | User Story | Kriteria Penerimaan |
| --- | --- | --- | --- |
| US-07 | Sales | Sebagai sales, saya ingin mendaftarkan outlet baru (NOO - New Outlet Opening) lengkap dengan lokasi, foto, dan data wilayah, agar outlet baru tercatat resmi dalam sistem | Form pendaftaran outlet dengan validasi koordinat GPS dan foto wajib; data langsung masuk ke Laporan Outlet NOO |
| US-08 | Admin / Koordinator | Sebagai admin atau koordinator, saya ingin melihat daftar seluruh outlet (Lap. Outlet All) dengan filter warehouse/depo, koordinator, sales, area, provinsi hingga kelurahan, agar saya dapat memantau sebaran outlet secara menyeluruh | Filter multi-level berfungsi, hasil dapat diekspor ke Excel, dan riwayat export tersimpan |
| US-09 | Admin / SSK | Sebagai admin atau supervisor, saya ingin melihat laporan outlet berdasarkan status AC (Active Customer), EC (Existing Customer), RO (Repeat Order), dan OSS, agar saya dapat mengukur status keaktifan tiap outlet | Masing-masing laporan (Lap. Outlet AC/EC/RO/OSS) menampilkan data sesuai kategori status outlet |
| US-10 | Admin / Manajemen | Sebagai admin, saya ingin mendeteksi dan meninjau outlet yang terindikasi fiktif, agar data outlet tetap valid dan tidak merugikan evaluasi kinerja sales | Laporan Outlet Fiktif menandai outlet dengan indikasi kejanggalan (misal lokasi tidak sesuai) untuk ditinjau |
| US-11 | Koordinator | Sebagai koordinator, saya ingin melakukan join visit (kunjungan bersama) ke outlet dengan sales binaan saya, agar saya dapat memverifikasi kualitas kunjungan secara langsung | Fitur Join Visit mencatat kehadiran koordinator bersama sales pada outlet yang sama dengan timestamp dan lokasi |
| US-12 | Admin / SSK | Sebagai admin atau supervisor, saya ingin melihat laporan join visit, agar saya dapat memantau frekuensi pendampingan koordinator terhadap sales | Lap. Join Visit menampilkan rekap kunjungan bersama per koordinator/sales dengan filter periode |

## Modul Aktivitas Canvassing

| Kode | Peran | User Story | Kriteria Penerimaan |
| --- | --- | --- | --- |
| US-13 | Sales | Sebagai sales, saya ingin check-in di outlet saat kunjungan dengan foto dan lokasi, agar bukti kunjungan tercatat valid dan tidak bisa dimanipulasi | Check-in memerlukan foto dan koordinat GPS real-time yang dicocokkan dengan lokasi outlet terdaftar |
| US-14 | Sales | Sebagai sales, saya ingin mencatat transaksi penjualan barang langsung di outlet, agar data penjualan harian terekam otomatis ke sistem | Form penjualan barang mencatat produk, jumlah, dan harga; data tersinkron ke laporan pencapaian |
| US-15 | Sales | Sebagai sales, saya ingin mencatat stok barang yang tersisa di outlet, agar perusahaan dapat memantau ketersediaan produk di lapangan | Form Stok Outlet menyimpan jumlah stok per SKU per outlet per kunjungan |
| US-16 | Sales | Sebagai sales, saya ingin mencatat keberadaan dan stok produk kompetitor di outlet, agar tim dapat menganalisis posisi produk terhadap kompetitor | Form Stok Kompetitor mencatat nama produk kompetitor, jumlah, dan harga jual di outlet |
| US-17 | Sales | Sebagai sales, saya ingin mencatat pengembalian/retur barang dari outlet, agar stok dan laporan penjualan tetap akurat | Form Pengembalian Barang mengurangi stok terjual dan mencatat alasan retur |
| US-18 | Admin / SSK | Sebagai admin atau supervisor, saya ingin melihat laporan aktivitas canvassing (checkin, penjualan, stok), agar saya dapat mengevaluasi produktivitas kunjungan sales | Laporan aktivitas canvassing dapat difilter per sales/periode dan diekspor |

## Modul Setoran & Distributor

| Kode | Peran | User Story | Kriteria Penerimaan |
| --- | --- | --- | --- |
| US-19 | Sales / Motoris | Sebagai sales atau motoris, saya ingin mencatat setoran hasil penjualan harian, agar uang hasil penjualan yang saya setorkan tercatat dan dapat direkonsiliasi oleh admin | Form Setoran mencatat nominal, tanggal, dan bukti setoran; status terverifikasi oleh admin |
| US-20 | Sales / Motoris | Sebagai sales atau motoris, saya ingin mencatat setoran margin secara terpisah dari setoran utama, agar perhitungan margin penjualan transparan | Form Setor Margin mencatat nominal margin per periode dan terhubung ke rekap pencapaian |
| US-21 | Admin | Sebagai admin, saya ingin mengelola data distributor (depo/warehouse), agar struktur distribusi barang ke sales/motoris tertata dengan baik | Master Distributor menyimpan profil depo, wilayah cakupan, dan relasi ke koordinator/sales |
| US-22 | Admin / SSK | Sebagai admin atau supervisor, saya ingin melihat laporan retur barang dari seluruh sales/motoris, agar saya dapat memantau tingkat retur dan penyebabnya | Laporan retur barang dapat difilter per depo/periode dan menampilkan alasan retur |
| US-23 | Admin / Manajemen | Sebagai admin, saya ingin melihat laporan rekap setoran dan setor margin seluruh tim, agar rekonsiliasi keuangan harian dapat dilakukan tepat waktu | Laporan setoran menampilkan status terverifikasi/belum, total nominal per depo/sales, dan dapat diekspor |

## Modul Tracking & Monitoring

| Kode | Peran | User Story | Kriteria Penerimaan |
| --- | --- | --- | --- |
| US-24 | SSK / Koordinator | Sebagai supervisor atau koordinator, saya ingin melihat lokasi terakhir (last seen) sales secara real-time, agar saya dapat memastikan sales berada di wilayah kerjanya | Tracking Last Seen Sales menampilkan titik lokasi terbaru pada peta dengan waktu update |
| US-25 | SSK / Admin | Sebagai supervisor atau admin, saya ingin melihat riwayat perjalanan (history) lokasi sales sepanjang hari, agar saya dapat menganalisis rute dan efisiensi kunjungan | Tracking History Sales Location menampilkan jejak rute pada peta per tanggal yang dipilih |
| US-26 | Admin / Manajemen | Sebagai admin, saya ingin melihat lokasi terakhir koordinator, agar pengawasan berjenjang terhadap koordinator juga dapat dipantau | Tracking Last Seen Koordinator menampilkan posisi koordinator terkini |
| US-27 | Admin / Manajemen | Sebagai admin, saya ingin melihat rekap coverage dan performance motoris, agar saya dapat menilai produktivitas dan cakupan area tiap motoris | Menu Motoris Coverage dan Motoris Performance menampilkan metrik kunjungan vs target per motoris |
| US-28 | Admin / Manajemen | Sebagai admin, saya ingin memantau absensi motoris, agar kedisiplinan motoris dalam operasional pengiriman/canvassing tetap terjaga | Monitor Absensi Motoris menampilkan status hadir/tidak hadir motoris per hari |
| US-29 | Admin / Manajemen | Sebagai admin, saya ingin melihat rekap pencapaian SKU per motoris dan per depo serta ringkasan seluruh depo, agar target penjualan per produk dapat dievaluasi secara agregat | Rekap Pencapaian SKU per Motoris/Depo dan Summary All Depo menampilkan pencapaian vs target dengan filter periode |
| US-30 | Admin / Manajemen | Sebagai admin, saya ingin melihat daftar sales yang tidak melakukan penjualan pada hari tertentu, agar tindak lanjut dapat segera dilakukan | Laporan Sales Tidak Penjualan menampilkan daftar sales tanpa transaksi harian |

## Modul User, Karyawan & Manajemen Akses

| Kode | Peran | User Story | Kriteria Penerimaan |
| --- | --- | --- | --- |
| US-31 | Admin | Sebagai admin, saya ingin membuat dan mengelola akun pengguna (Users) beserta hak aksesnya, agar setiap peran hanya dapat mengakses fitur yang relevan | Admin dapat membuat/mengedit/menonaktifkan user, menetapkan peran (sales, koordinator, SSK, admin) |
| US-32 | Admin / HR | Sebagai admin, saya ingin mengelola data master karyawan (Karyawan), agar data kepegawaian terhubung dengan modul absensi dan penggajian | Data karyawan mencakup identitas, jabatan, area kerja, dan status aktif/non-aktif |
| US-33 | Admin | Sebagai admin, saya ingin memantau sesi login pengguna, agar saya dapat mendeteksi aktivitas login yang mencurigakan atau akun yang digunakan ganda | Menu Sesi Login menampilkan riwayat login dengan perangkat dan waktu akses |
| US-34 | Admin / Manajemen | Sebagai admin, saya ingin mengakses laporan terkait user dan karyawan, agar saya memiliki gambaran menyeluruh tentang tenaga kerja aktif | Laporan user/karyawan dapat difilter dan diekspor sesuai kebutuhan manajemen |

## Modul Master Data & Pengaturan

| Kode | Peran | User Story | Kriteria Penerimaan |
| --- | --- | --- | --- |
| US-35 | Admin | Sebagai admin, saya ingin mengelola manajemen produk (SKU, kategori, harga), agar data produk yang digunakan di seluruh modul penjualan dan stok selalu konsisten | Master Produk mendukung tambah/edit/nonaktifkan SKU dengan kategori dan harga acuan |
| US-36 | Admin | Sebagai admin, saya ingin mengatur konfigurasi sistem (misal radius absen, format laporan), agar aplikasi dapat disesuaikan dengan kebijakan operasional perusahaan | Menu Konfigurasi menyimpan parameter sistem dan berlaku di seluruh modul terkait |
| US-37 | Admin | Sebagai admin, saya ingin mengelola master data wilayah (provinsi, kota/kab, kecamatan, kelurahan) dan area kerja, agar filter dan pelaporan berbasis wilayah akurat | Master Data wilayah terstruktur hierarkis dan digunakan konsisten di seluruh filter laporan |
| US-38 | Admin / Manajemen | Sebagai admin, saya ingin membuat pengumuman untuk seluruh pengguna aplikasi, agar informasi penting (kebijakan, promo, perubahan sistem) tersampaikan ke tim lapangan | Menu Pengumuman dapat dipublikasikan dan tampil sebagai notifikasi kepada pengguna terkait |
| US-39 | Semua Pengguna | Sebagai pengguna aplikasi, saya ingin mengakses panduan penggunaan aplikasi, agar saya dapat memahami cara menggunakan setiap fitur tanpa bantuan langsung dari admin | Menu Panduan berisi dokumentasi/FAQ yang dapat diakses dari dalam aplikasi |

## Ringkasan & Catatan

Dokumen ini memuat 39 user story yang mencakup 7 modul utama aplikasi Marketama: Absensi & Penggajian, Outlet & Visit, Aktivitas Canvassing, Setoran & Distributor, Tracking & Monitoring, User/Karyawan & Akses, serta Master Data & Pengaturan.

Prioritas tinggi yang disarankan untuk tahap awal pengembangan/evaluasi: pencatatan outlet dan validasi anti-fiktif (US-07, US-10), check-in absen dan kunjungan dengan validasi GPS (US-01, US-13), serta pencatatan penjualan dan setoran harian (US-14, US-19) karena menjadi inti alur kerja operasional harian sales.

Catatan: user story disusun berdasarkan observasi struktur menu dan satu halaman laporan yang dibuka langsung (Laporan Outlet NOO). Detail alur kerja, validasi field, dan aturan bisnis spesifik tiap fitur disarankan dikonfirmasi lebih lanjut dengan pemilik produk/tim Marketama untuk penyempurnaan kriteria penerimaan.
