# Marketama Motorist System

Konteks untuk pembaruan Marketama Motorist System: admin penjualan keliling (canvassing) yang mengelola sales di lapangan, koordinator, depo, outlet, stok, absensi, dan penggajian.

## Bahasa

### Perusahaan

| Istilah | Arti | Hindari |
| --- | --- | --- |
| **PT. Marketama** | Perusahaan pemilik dan pengguna sistem ini; anak perusahaan dari Enesis Group. | |
| **Enesis Group** | Perusahaan induk dari PT. Marketama. | |

### Operasi lapangan

| Istilah | Arti | Hindari |
| --- | --- | --- |
| **Canvassing** | Penjualan keliling: sales membawa barang dari depo dan menjualnya langsung ke outlet. | Door-to-door, Penjualan kanvas |
| **Sales** | Karyawan lapangan yang membawa barang canvassing dan mengunjungi outlet. | Salesman |
| **Motoris** | Sales yang berkeliling dengan sepeda motor; istilah yang dipakai pada laporan pencapaian, coverage, dan performance. | |
| **Kuota Motoris** | Jumlah sales motoris yang disediakan per depo, diisi admin; dasar Motoris Report. | |
| **Koordinator** | Karyawan yang mengawasi sekelompok sales di lapangan dan punya jejak GPS sendiri. | Korlap |
| **Supervisor (SSK)** | Karyawan di atas koordinator; punya absensi dan data karyawan tersendiri. | |
| **Project** | Pengelompokan kerja/klien tempat sales dan depo ditugaskan; dikelola di menu Master Project. | |

### Wilayah dan depo

| Istilah | Arti | Hindari |
| --- | --- | --- |
| **Depo** | Tempat barang canvassing dimuat sebelum sales berangkat dan dikembalikan sesudahnya. | Gudang |
| **Wilayah Depo** | Cakupan geografis yang dilayani sebuah depo. | |
| **Area** | Wilayah kerja bergambar polygon di peta yang dirancang lewat Design Polygon Area. | Zona |
| **Checkin / Checkout Depo** | Pencatatan kehadiran sales di depo saat mulai dan selesai hari kerja. | |
| **Hari Kerja Aktif** | Hari ketika aplikasi lapangan boleh dipakai; selain itu libur (lihat Tanggal Libur). | |

### Barang dan stok

| Istilah | Arti | Hindari |
| --- | --- | --- |
| **Barang Canvassing** | Barang yang dibawa sales dari depo untuk dijual pada satu hari canvassing. | |
| **Template Barang Canvassing** | Daftar barang baku yang dipakai sebagai dasar muatan Barang Canvassing. | |
| **Loading Barang** | Pemuatan barang dari depo ke sales sebelum berangkat. | |
| **Pengembalian Barang** | Barang canvassing yang tidak terjual dan dikembalikan sales ke depo. | |
| **Retur Barang** | Barang yang dikembalikan karena rusak/ditolak, berbeda dari barang sisa yang tidak terjual. | |
| **Penjualan** | Transaksi penjualan barang canvassing dari sales ke outlet. | |
| **Setoran** | Uang hasil penjualan yang disetor sales ke depo. | |
| **Setor Margin** | Penyetoran bagian margin dari hasil penjualan. | |
| **SKU** | Satu varian produk yang dapat dijual; dasar rekap pencapaian per motoris dan per depo. | |
| **Produk Kompetitor** | Produk pesaing yang dicatat stoknya di outlet. | |

### Outlet dan kunjungan

| Istilah | Arti | Hindari |
| --- | --- | --- |
| **Outlet** | Toko/warung pelanggan yang dikunjungi sales. | |
| **Checkin Outlet** | Pencatatan kunjungan sales ke sebuah outlet. | |
| **New Open Outlet (NOO)** | Outlet yang baru pertama kali dibuka/ditransaksikan. | |
| **Outlet Baru dari HP** | Outlet yang didaftarkan sales lewat aplikasi HP; ditampilkan di menu All Outlet terpisah dari Master Outlet. | |
| **Actual Call (AC)** | Kunjungan ke outlet yang tercatat benar-benar terjadi. | |
| **Effective Call (EC)** | Kunjungan yang berujung pembelian. | |
| **Repeat Order (RO)** | Outlet yang membeli ulang. | |
| **Out of Stock (OSS)** | Kondisi outlet kehabisan stok produk. | Stock out |
| **Outlet Duplikat / Fiktif** | Outlet yang terdaftar ganda atau tidak nyata, ditandai agar tidak dihitung. | |
| **Join Visit** | Kunjungan bersama atasan (koordinator/supervisor) dengan sales ke outlet. | |
| **Tracking Last Seen** | Posisi GPS terakhir sales atau koordinator. | |
| **Tracking History** | Rute GPS sales atau koordinator selama periode tertentu. | |

### SDM dan penggajian

| Istilah | Arti | Hindari |
| --- | --- | --- |
| **Karyawan** | Orang yang bekerja di lapangan atau kantor, dikategorikan (supervisor, koordinator, sales, lainnya). | |
| **User** | Akun login ke sistem; terpisah dari Karyawan dan dikategorikan (Super Admin, Koordinator, Sales, Lainnya). | |
| **Absensi** | Kehadiran harian karyawan; dipisah untuk sales, koordinator, dan supervisor. | |
| **Permintaan Izin** | Pengajuan tidak masuk (izin/sakit) oleh karyawan. | |
| **Surat Peringatan** | Sanksi tertulis kepada karyawan, dikelompokkan per Kategori Surat Peringatan. | |
| **Insentif** | Tambahan pendapatan sales/koordinator yang dihitung dengan rumus insentif dan konversi poin per depo. | |
| **Penggajian** | Perhitungan gaji karyawan dari absensi, insentif, dan potongan gaji; saat ini tersedia untuk koordinator. | |
| **Potongan Gaji** | Pengurang gaji berdasarkan parameter pemotong gaji, mis. keterlambatan. | |
