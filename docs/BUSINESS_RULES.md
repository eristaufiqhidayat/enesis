# Aturan Bisnis Operasional Motoris

Dokumen ini adalah **sumber utama dan satu-satunya** tentang aturan bisnis Marketama dalam menjalankan operasional motoris (sales keliling) — dari absen pagi sampai setoran dan insentif. Lihat juga gambaran produk di [docs/PRD.md](PRD.md) dan backlog fitur di [docs/BACKLOG.md](BACKLOG.md); dokumen ini khusus memuat **aturan**, bukan status pengerjaan fitur.

## Cara memakai & mengisi dokumen ini

- Setiap aturan ditulis sebagai pernyataan ("Jika X maka Y"), bukan deskripsi fitur, disertai status:
  - **Dikonfirmasi** — sudah diverifikasi dengan product owner PT. Marketama dan/atau sudah diimplementasikan & diuji (sebutkan PR/spec/kode terkait).
  - **Asumsi** — disimpulkan dari data/perilaku aplikasi lama, belum divalidasi dengan product owner.
  - **Belum diketahui** — pertanyaan terbuka, perlu ditelusuri atau ditanyakan.
- **Setiap poin memuat keputusan akhir yang berlaku saat ini — bukan narasi historis.** Jangan menulis "sebelumnya X, lalu diubah jadi Y" di badan aturan; cukup tulis Y sebagai aturan yang berlaku. Riwayat keputusan yang berubah/saling membatalkan dicatat **khusus** di bagian [Riwayat perubahan aturan](#riwayat-perubahan-aturan).
- **Catatan kesenjangan kode vs aturan (⚠️) ditulis sekali saja**, terkumpul di bagian [Status implementasi](#14-status-implementasi), bukan diselipkan di tiap poin aturan — supaya badan aturan tetap bersih, dan status kode mudah dipantau di satu tempat.
- Tiap aturan mencantumkan sumber bila relevan: nomor spec (`docs/backlog_items/NNN-.../spec.md`), lokasi kode (`App\...`), atau "Marketama (observasi UI)".
- Istilah Marketama yang dipakai apa adanya didaftar di [Glosarium](#glosarium) agar konsisten di seluruh dokumen.

## Daftar isi

1. [Peran & struktur tim](#1-peran--struktur-tim)
2. [Area kerja & master wilayah](#2-area-kerja--master-wilayah)
3. [Absen Datang/Pulang di Depo](#3-absen-datangpulang-di-depo)
4. [Loading barang di Depo](#4-loading-barang-di-depo)
5. [Rute & Check In Outlet](#5-rute--check-in-outlet)
6. [Penjualan (transaksi canvassing)](#6-penjualan-transaksi-canvassing)
7. [Stok: outlet, kompetitor, retur](#7-stok-outlet-kompetitor-retur)
8. [Setoran & setor margin](#8-setoran--setor-margin)
9. [Target & insentif](#9-target--insentif)
10. [Izin & potongan](#10-izin--potongan)
11. [Penggajian](#11-penggajian)
12. [Outlet: status, duplikat & fiktif](#12-outlet-status-duplikat--fiktif)
13. [Notifikasi & eskalasi](#13-notifikasi--eskalasi)
14. [Status implementasi](#14-status-implementasi)
15. [Glosarium](#glosarium)
16. [Riwayat perubahan aturan](#riwayat-perubahan-aturan)

---

## 1. Peran & struktur tim

_Siapa yang melakukan apa: motoris/sales, koordinator, staf depo/warehouse, HR/penggajian, Super Admin. Hierarki `mk_karyawan.koordinator_id`, siapa boleh menyetujui apa._

- **Dikonfirmasi** — Salah satu tugas Koordinator adalah **memvalidasi (setujui/tolak) NOO** (outlet baru) yang dibuat motoris bawahannya. Detail alur: lihat §12.
- **Dikonfirmasi** — Koordinator berkewajiban melakukan **Absen Pulang atas nama motoris bawahannya** bila motoris itu sudah retur barang bawaan dan sudah setoran hasil penjualan, tapi belum Absen Pulang sendiri. Detail: lihat §3.
- _(TBD lainnya)_

## 2. Area kerja & master wilayah

_Bagaimana area kerja ditentukan dan dipakai untuk validasi — bukan hanya struktur data (sudah dijelaskan di CLAUDE.md), tapi aturan: siapa punya area aktif, apa yang terjadi kalau tidak ada area aktif, bagaimana area koordinator dihitung dari gabungan tim._

- **Dikonfirmasi** — Area kerja (polygon `mk_karyawan_area` aktif milik sales; untuk koordinator = gabungan/union area aktif seluruh sales bawahannya) dipakai untuk membatasi **rute & kunjungan outlet** (lihat §5 "di luar rute") — **bukan** untuk validasi Absen (lihat §3). Akun tanpa area kerja aktif ditolak oleh fitur yang memakainya (`409 no_work_area`).
- _(TBD lainnya)_

## 3. Absen Datang/Pulang di Depo

_Istilah yang benar: motoris melakukan **Absen Datang** dan **Absen Pulang** di Depo (bukan "checkin depo") — bukan di outlet. "Check In" sebagai istilah dipakai khusus untuk outlet (lihat §5). Jam absen, selfie, validasi lokasi, absen ganda, hari libur, cuti — kapan "hari kerja" dianggap mulai/selesai._

- **Dikonfirmasi** — Absen Datang dan Absen Pulang terjadi **di lokasi Depo**: validasi GPS saat absen divalidasi terhadap lokasi/radius Depo, bukan terhadap area kerja (polygon).
- **Dikonfirmasi** — Jika motoris sudah **retur barang bawaan** (§4) dan sudah **setoran hasil penjualan** (§8) tapi **belum Absen Pulang**, maka **Koordinator wajib melakukan Absen Pulang atas nama motoris itu**.
- **Dikonfirmasi** — Konsekuensi absen telat (toleransi 3x/bulan, denda potongan mulai telat ke-4): lihat §10.
- **Dikonfirmasi** — Absen Datang **dan** Absen Pulang di Depo wajib disertai **foto selfie**.
- _(TBD: jam batas absen datang, aturan absen ganda per hari, dll.)_

## 4. Loading barang di Depo

_Apa yang terjadi di Depo selain Absen Datang/Pulang: loading barang canvassing (jumlah, stok depo, siapa yang approve), retur, setor margin (lihat juga §8)._

- **Dikonfirmasi** — Motoris baru bisa melakukan loading barang canvassing setelah **Absen Datang** (§3): nama motoris baru muncul di **daftar loading barang canvassing** pada aplikasi Sales Koordinator setelah motoris itu Absen Datang hari itu. Motoris yang belum Absen Datang tidak muncul di daftar, sehingga belum bisa di-loading-kan barang.
- _(TBD: apakah loading barang adalah event tersendiri yang perlu dicatat terpisah dari Absen Datang/Pulang, atau menyatu dengannya; batas jumlah loading, approval, hubungan dengan stok depo)_

## 5. Rute & Check In Outlet

_Istilah yang benar: "Check In" dipakai motoris **hanya untuk Outlet (toko)** — bukan untuk Depo (lihat §3). Urutan kunjungan outlet, radius checkin outlet, status NOO/AC/EC/RO/OSS dan kapan outlet pindah status, Join Visit._

- **Dikonfirmasi** — Check In/Check Out hanya berlaku untuk kunjungan Outlet (toko), bukan Depo.
- **Dikonfirmasi** — Urutan kunjungan outlet: Check In → (boleh buat & submit transaksi) → Check Out. Motoris baru boleh membuat transaksi penjualan setelah Check In; Check Out dilakukan setelah transaksi di-submit.
- **Dikonfirmasi** — Check In outlet divalidasi terhadap posisi GPS motoris vs titik GPS outlet, **toleransi 5 meter** — di luar itu Check In ditolak.
- **Dikonfirmasi** — Check In outlet **pertama** pada hari kerja punya batas waktu: paling lambat **jam 11.00 waktu setempat**. Lewat jam itu, motoris **tidak dapat Check In outlet sama sekali pada hari itu** (bukan hanya Check In pertama yang ditolak — seluruh Check In outlet hari itu tertutup).
- _(TBD: apa yang harus dilakukan motoris pada sisa hari itu setelah terlewat batas jam Check In — langsung Absen Pulang, atau tetap bertugas di Depo/aktivitas lain lalu Absen Pulang normal di sore hari; belum diputuskan product owner)_
- **Asumsi** — Setelah Check In outlet, motoris dapat melakukan salah satu dari aktivitas berikut sebelum Check Out: (1) melakukan penjualan (§6), (2) laporan stok barang Enesis yang habis di outlet (§7), (3) laporan jumlah stok barang kompetitor di outlet (§7), atau (4) laporan toko tutup (§7). Belum divalidasi dengan product owner apakah ini daftar lengkap atau ada aktivitas lain.
- _(TBD: status NOO/AC/EC/RO/OSS dan kapan outlet pindah status, aturan Join Visit, outlet di luar rute)_

## 6. Penjualan (transaksi canvassing)

_Aturan harga, diskon, retur saat transaksi, syarat outlet boleh dijual (status, utang, dll.), effective call._

- **Dikonfirmasi** — Transaksi penjualan hanya boleh dibuat dalam kunjungan outlet yang sudah Check In dan belum Check Out; setelah transaksi di-submit, motoris melakukan Check Out untuk menutup kunjungan. — _lihat §5; kode: `App\Http\Controllers\Mobile\KunjunganController::terbuka()`_
- **Dikonfirmasi** — Motoris hanya dapat men-submit transaksi penjualan jika **totalnya melebihi ambang batas** tertentu (di bawah ambang batas, submit ditolak).
  - **Belum diketahui** — nilai ambang batasnya. Juga belum diputuskan apakah ambang batas ini **sama dengan** `minimal_value_transaksi` per depo yang sudah ada di master data (dipakai untuk status Effective Call, lihat kode terkait di §14) atau **ambang batas baru yang berbeda**.
  - **Rekomendasi** (bukan keputusan) — jadikan **parameter yang bisa diatur admin per depo**, bukan nilai tetap di kode: preseden sudah ada (`minimal_value_transaksi` per depo di master data, dengan fallback `config('mobile.minimal_transaksi_default')`), jadi kemungkinan besar cukup dipakai ulang jika memang ambang batas yang sama — bukan dibuat parameter terpisah.
- _(TBD: aturan harga, diskon, retur saat transaksi, syarat outlet boleh dijual)_

## 7. Stok: outlet, kompetitor, retur

_Pencatatan stok outlet & kompetitor saat visit, aturan retur barang canvassing._

- **Asumsi** — Dalam satu kunjungan outlet (lihat §5), motoris dapat melaporkan: stok barang Enesis yang habis di outlet itu, dan jumlah stok barang kompetitor di outlet itu. — _kecocokan dengan kode: `App\Http\Controllers\Mobile\KunjunganController::stok()` menerima `items` (stok sendiri) dan `competitors` (stok kompetitor) dalam satu panggilan; belum divalidasi dengan product owner apakah ini memang aturan resminya, bukan sekadar perilaku kode yang ada._
- **Asumsi** — Motoris dapat melaporkan **toko tutup** sebagai alasan tidak melakukan transaksi di kunjungan itu. — _kecocokan dengan kode: `toko_tutup` sudah ada sebagai salah satu `alasan_tidak_beli` ([config/mobile.php:53](../config/mobile.php)), dipakai lewat `KunjunganController::tidakBeli()`; belum divalidasi dengan product owner._
- _(TBD: aturan retur barang canvassing)_

## 8. Setoran & setor margin

_Aturan setoran hasil penjualan, setor margin ke depo, tenggat, selisih/kekurangan setoran._

- _(TBD)_

## 9. Target & insentif

_Rumus insentif per depo/produk, cara target ditetapkan, konversi poin, periode perhitungan._

- **Belum diketahui** — rumus insentif dan potongan di Marketama hanya tampak sebagai data referensi (CSV), logika perhitungannya belum teramati. — _docs/PRD.md §5_
- _(TBD setelah penelusuran alur tulis Absensi/Penggajian — lihat PRD §7 fase 7)_

## 10. Izin & potongan

_Jenis izin, alur persetujuan, jenis potongan gaji dan pemicunya._

- **Dikonfirmasi** — Keterlambatan absen (lihat §3) ditoleransi **3 kali per bulan** tanpa sanksi. Mulai keterlambatan ke-4 dan seterusnya **dalam bulan yang sama**, motoris dikenakan **denda potongan** (gaji — lihat §11).
- _(TBD: jenis izin lain, alur persetujuan, jenis potongan gaji lain dan pemicunya)_

## 11. Penggajian

_Komponen gaji, periode, hubungan dengan absensi/insentif/potongan, siapa yang mem-finalisasi._

- **Catatan proses** — hasil gaji & insentif aplikasi baru harus identik dengan Marketama (toleransi disepakati) untuk minimal satu periode penuh sebelum cut-over; pemilik proses/validasi: Super Admin PT. Marketama. — _docs/PRD.md §9/§10_
- _(TBD)_

## 12. Outlet: status, duplikat & fiktif

_Kriteria outlet dianggap duplikat atau fiktif, siapa yang menandai/memvalidasi, dampaknya ke rute & penjualan._

- **Dikonfirmasi** — NOO (outlet baru, `trx_outlet_baru`) yang didaftarkan motoris lewat HP harus **divalidasi oleh Koordinator** (setuju/tolak) sebelum masuk ke master outlet (`mk_outlet`).
- _(TBD: kriteria outlet dianggap duplikat/fiktif dan siapa yang menandainya — beda dari validasi NOO di atas)_

## 13. Notifikasi & eskalasi

_Kapan sistem/koordinator diberi tahu (absen telat, outlet fiktif, setoran kurang, dll.)._

- _(TBD)_

---

## 14. Status implementasi

Daftar kesenjangan antara aturan di atas dan kode yang berjalan saat ini. Perbarui/hapus baris di sini begitu kode sudah disesuaikan — badan aturan di §1–§13 tidak perlu diubah karena aturannya sendiri tidak berubah.

- **§2 / §3 — Lokasi validasi Absen**: kode saat ini (`App\Support\Mobile\WilayahKerja`, endpoint `attendances/check-in|check-out`) masih memvalidasi Absen terhadap **area kerja** (point-in-polygon, `422 outside_work_area`), bukan terhadap **lokasi Depo** seperti aturan §3. Perlu ditinjau ulang.
- **§3 — Selfie wajib saat Absen Pulang**: `App\Http\Controllers\Mobile\AbsensiController::checkIn` (absen datang) sudah mewajibkan `photo_id` (`photo_id.required` "Foto selfie wajib"), tapi `checkOut` (absen pulang) tidak mewajibkan foto sama sekali — validasinya hanya `aturanUmum() + aturanLokasi(false)`, tanpa `photo_id`. Perlu ditambahkan.
- **§3 — Absen Pulang oleh Koordinator**: tidak ada endpoint di `App\Http\Controllers\Mobile\KoordinatorController` atau `AbsensiController` untuk Koordinator mengabsenkan-pulangkan bawahannya; absen pulang (`pulang_at`, lihat `KoordinatorController.php:126-127`) hanya bisa diisi oleh motoris sendiri lewat akunnya. Terbuka: siapa/apa yang memicu pengecekan ini (otomatis oleh sistem saat retur+setoran lengkap, atau Koordinator mengecek manual di akhir hari), dan apakah ada tenggat waktu untuk Koordinator melakukannya.
- **§4 — Daftar loading barang canvassing**: tidak ada endpoint mobile untuk "daftar loading barang canvassing" (tidak ditemukan controller loading barang di `App\Http\Controllers\Mobile`); yang ada baru laporan read-only di web (`App\Http\Controllers\LapLoadingBarangController`, menu "Lap. Loading Barang"), yang juga tidak memfilter berdasarkan status Absen Datang. Perlu dirancang: endpoint/menu daftar loading barang untuk aplikasi Koordinator, dengan filter "sudah Absen Datang hari ini".
- **§5 — Radius Check In outlet**: `App\Http\Controllers\Mobile\KunjunganController::checkIn` (error `too_far_from_outlet`) memvalidasi terhadap `config('mobile.radius_outlet_m')`, default **100 meter** (`MOBILE_RADIUS_OUTLET_M`, [config/mobile.php:21](../config/mobile.php)) — bukan 5 meter seperti aturan §5. Perlu diputuskan: ubah konfigurasi jadi 5 m, atau 5 m di aturan itu maksudnya toleransi akurasi GPS yang berbeda dari radius batas Check In.
- **§6 — Ambang batas submit transaksi**: `App\Http\Controllers\Mobile\PenjualanController::simpan` saat ini **tidak memblokir submit** berapa pun totalnya — `minimal_value_transaksi` per depo (fallback `config('mobile.minimal_transaksi_default')`, lihat `App\Support\Mobile\Pengaturan::depo()`) hanya dipakai untuk menandai `is_effective_call`, bukan untuk menolak transaksi. Kalau aturan §6 memang memakai ambang batas yang sama, perilaku kode ini perlu diubah dari "izinkan + tandai EC" menjadi "tolak di bawah ambang batas".
- **§5 — Jam batas Check In outlet pertama**: `KunjunganController::checkIn` dan `App\Support\Mobile\HariKerja` saat ini tidak punya pengecekan jam sama sekali. Terbuka: "waktu setempat" dihitung dari zona waktu apa (zona outlet/area kerja, atau zona depo/akun), dan kode error baru untuk penolakan ini (mis. `check_in_window_closed`).
- **§10 — Denda keterlambatan absen**: `App\Http\Controllers\Mobile\AbsensiController` sudah menandai setiap absen `terlambat`/`is_late` (baris 63, 76, 84, 126), tapi **tidak ada penghitungan jumlah keterlambatan per bulan** maupun logika pemicu denda/potongan di manapun di kode. Terbuka: nominal/rumus denda per keterlambatan (tetap, atau persentase gaji?), apakah hitungan 3x reset tiap tanggal 1 atau per periode gajian.
- **§12 — Validasi NOO oleh Koordinator**: implementasi saat ini (`App\Http\Controllers\OutletBaruController::setujui/tolak`, dipakai dari menu web "All Outlet" tab "Outlet Baru dari HP") tidak mengecek peran Koordinator secara spesifik; siapa pun akun web dengan **cakupan akses** ke sales tersebut (`App\Support\Akses\Akses::batasiKaryawan` — depo/downline/diri sendiri) dan hak ke menu itu bisa menyetujui/menolak. Tidak ada endpoint di aplikasi HP (`App\Http\Controllers\Mobile\KoordinatorController`) untuk Koordinator melakukan ini dari HP-nya sendiri. Perlu diputuskan: apakah validasi NOO cukup dibatasi lewat hak menu web (role "Koordinator" di tabel role), atau perlu ditambah alur approval di aplikasi HP koordinator.

## Glosarium

| Istilah | Arti |
| --- | --- |
| Motoris | Sales keliling yang berkendara motor, unit terkecil operasional canvassing |
| Koordinator | Atasan langsung sekelompok motoris/sales (`mk_karyawan.koordinator_id`) |
| Depo | Gudang/warehouse tempat motoris **Absen Datang/Pulang**, loading barang, dan setor hasil penjualan — bukan tempat "Check In" |
| Check In | Istilah untuk kunjungan **Outlet (toko)** saja — motoris check in/out di outlet, bukan di depo |
| Absen Datang / Absen Pulang | Istilah untuk kedatangan/kepulangan motoris di **Depo** — bukan "checkin depo" |
| Area kerja / Master Area | Polygon wilayah tanggung jawab seorang sales atau tim (`mk_area`, digambar lewat `area_polygon`) |
| NOO / AC / EC / RO / OSS | Status outlet (New Outlet Opening / Active Call / dst. — _lengkapi definisi resmi_) |
| Canvassing | Model penjualan keliling dari outlet ke outlet dengan barang dibawa langsung |

_(Lengkapi seiring penelusuran; jangan biarkan singkatan tanpa definisi.)_

## Riwayat perubahan aturan

Catat di sini hanya perubahan **aturan bisnis** (bukan refactor kode), terutama yang membatalkan keputusan sebelumnya, supaya tidak ada dua sumber yang saling kontradiksi. Badan dokumen di atas selalu memuat **keputusan akhir yang berlaku sekarang**; tabel ini adalah satu-satunya tempat untuk jejak historisnya.

| Tanggal | Aturan yang berubah | Dari → Ke | Alasan | Sumber |
| --- | --- | --- | --- | --- |
| 2026-10-28 | Validasi lokasi saat absen sales/koordinator | Radius depo → Area kerja (polygon, point-in-polygon) | Sales sudah di lapangan saat jam absen, bisa jauh dari depo; checkin depo (ambil barang) tetap soal hadir fisik di gudang | spec 035 (membatalkan spec 033, selisih keputusan 12 menit) |
| 2026-10-30 | Istilah & lokasi Absen vs Check In | "Checkin Depo"/"Absen" dianggap dua hal tak jelas lokasinya → **Absen Datang/Pulang terjadi di Depo**; **Check In hanya untuk Outlet**. Membatalkan keputusan spec 035 (yang memvalidasi absen terhadap area kerja, bukan depo) | Klarifikasi product owner: istilah operasional yang benar | Percakapan klarifikasi 2026-10-30 — implementasi kode (`attendances/check-in`, `WilayahKerja`) **belum disesuaikan**, lihat §14 |
