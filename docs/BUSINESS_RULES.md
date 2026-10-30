# Aturan Bisnis Operasional Motoris

Dokumen ini adalah **sumber utama dan satu-satunya** tentang aturan bisnis Marketama dalam menjalankan operasional motoris (sales keliling) — dari absen pagi sampai setoran dan insentif. Lihat juga gambaran produk di [docs/PRD.md](PRD.md) dan backlog fitur di [docs/BACKLOG.md](BACKLOG.md); dokumen ini khusus memuat **aturan**, bukan status pengerjaan fitur.

## Cara memakai & mengisi dokumen ini

- Setiap aturan ditulis sebagai pernyataan ("Jika X maka Y"), bukan deskripsi fitur, disertai status:
  - **Dikonfirmasi** — sudah diverifikasi dengan product owner PT. Marketama dan/atau sudah diimplementasikan & diuji (sebutkan PR/spec/kode terkait).
  - **Asumsi** — disimpulkan dari data/perilaku aplikasi lama, belum divalidasi dengan product owner.
  - **Belum diketahui** — pertanyaan terbuka, perlu ditelusuri atau ditanyakan.
- Tiap aturan mencantumkan sumber: nomor spec (`docs/backlog_items/NNN-.../spec.md`), lokasi kode (`App\...`), atau "Marketama (observasi UI)".
- Perubahan aturan (bukan sekadar implementasi ulang) dicatat di bagian [Riwayat perubahan aturan](#riwayat-perubahan-aturan) di bagian bawah, dengan tanggal dan alasan — supaya histori keputusan yang saling membatalkan (lihat contoh absen vs checkin depo) tidak hilang.
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
14. [Glosarium](#glosarium)
15. [Riwayat perubahan aturan](#riwayat-perubahan-aturan)

---

## 1. Peran & struktur tim

_Siapa yang melakukan apa: motoris/sales, koordinator, staf depo/warehouse, HR/penggajian, Super Admin. Hierarki `mk_karyawan.koordinator_id`, siapa boleh menyetujui apa._

- **Dikonfirmasi** (2026-10-30, klarifikasi product owner) — Salah satu tugas Koordinator adalah **memvalidasi (setujui/tolak) NOO** (outlet baru) yang dibuat motoris bawahannya. Detail alur & implementasi: lihat §12.
- _(TBD lainnya)_

## 2. Area kerja & master wilayah

_Bagaimana area kerja ditentukan dan dipakai untuk validasi — bukan hanya struktur data (sudah dijelaskan di CLAUDE.md), tapi aturan: siapa punya area aktif, apa yang terjadi kalau tidak ada area aktif, bagaimana area koordinator dihitung dari gabungan tim._

- ~~**Dikonfirmasi** — Absen sales divalidasi terhadap **area kerja** (polygon `mk_karyawan_area` aktif), bukan radius depo~~ — **dibatalkan 2026-10-30** (lihat §3 dan [Riwayat perubahan aturan](#riwayat-perubahan-aturan)): Absen Datang/Pulang terjadi di Depo, jadi semestinya divalidasi terhadap lokasi Depo, bukan area kerja. Implementasi saat ini (`App\Support\Mobile\WilayahKerja`, kode `409 no_work_area` / `422 outside_work_area`) masih memakai aturan lama — **belum disesuaikan**.
- **Dikonfirmasi** — Area kerja (polygon, union tim untuk koordinator) tetap relevan untuk hal lain selain absen — misalnya batas rute/kunjungan outlet (lihat §5 "di luar rute"). Area kerja tanpa polygon aktif → `409 no_work_area` untuk fitur yang memang memakainya.
- _(TBD: setelah §3 ditinjau ulang, pastikan di sini hanya menyisakan aturan area kerja yang benar-benar masih berlaku)_

## 3. Absen Datang/Pulang di Depo

_Istilah yang benar: motoris melakukan **Absen Datang** dan **Absen Pulang** di Depo (bukan "checkin depo") — bukan di outlet. "Check In" sebagai istilah dipakai khusus untuk outlet (lihat §5). Jam absen, selfie, validasi lokasi, absen ganda, hari libur, cuti — kapan "hari kerja" dianggap mulai/selesai._

- **Dikonfirmasi** (2026-10-30, klarifikasi product owner) — Absen Datang dan Absen Pulang terjadi **di lokasi Depo**: validasi GPS saat absen semestinya terhadap lokasi/radius Depo, bukan terhadap area kerja (polygon). Ini **membatalkan sebagian keputusan spec 035**, yang memvalidasi absen terhadap area kerja justru karena beranggapan motoris sudah di lapangan saat jam absen. Implementasi di `App\Support\Mobile\WilayahKerja` dan endpoint `attendances/check-in|check-out` perlu ditinjau ulang agar sesuai — **belum dikerjakan**, lihat [Riwayat perubahan aturan](#riwayat-perubahan-aturan).
- _(TBD: jam batas absen datang, syarat selfie, apa yang terjadi jika absen telat, aturan absen ganda per hari, dll.)_

## 4. Loading barang di Depo

_Apa yang terjadi di Depo selain Absen Datang/Pulang: loading barang canvassing (jumlah, stok depo, siapa yang approve), retur, setor margin (lihat juga §8)._

- **Dikonfirmasi** — Sebelum klarifikasi 2026-10-30, "Checkin/Checkout Depo" (`POST /depot-visits/check-in|check-out`, divalidasi terhadap radius depo `mobile.radius_depo_m`) dipakai sebagai event terpisah dari absen — _spec 035_. Dengan koreksi istilah di §3 (absen sendiri yang terjadi di Depo), status event "checkin/checkout depo" ini sebagai hal yang berbeda dari Absen Datang/Pulang **perlu dikonfirmasi ulang**: apakah masih ada event terpisah untuk loading barang (datang ke Depo untuk ambil barang, bukan sekadar absen), atau keduanya sebenarnya satu kejadian yang sama.
- _(TBD: aturan loading barang — batas jumlah, approval, hubungan dengan stok depo)_

## 5. Rute & Check In Outlet

_Istilah yang benar: "Check In" dipakai motoris **hanya untuk Outlet (toko)** — bukan untuk Depo (lihat §3). Urutan kunjungan outlet, radius checkin outlet, status NOO/AC/EC/RO/OSS dan kapan outlet pindah status, Join Visit._

- **Dikonfirmasi** (2026-10-30, klarifikasi product owner) — Check In hanya berlaku untuk kunjungan Outlet (toko), bukan Depo.
- **Dikonfirmasi** (2026-10-30, klarifikasi product owner) — Urutan kunjungan outlet: Check In → (boleh buat & submit transaksi) → Check Out. Motoris baru boleh membuat transaksi penjualan setelah Check In; Check Out dilakukan setelah transaksi di-submit.
- **Dikonfirmasi** (2026-10-30, klarifikasi product owner) — Check In outlet divalidasi terhadap posisi GPS motoris vs titik GPS outlet, **toleransi 5 meter** — di luar itu Check In ditolak.
  - ⚠️ **Perlu ditinjau** — implementasi saat ini (`App\Http\Controllers\Mobile\KunjunganController::checkIn`, error `too_far_from_outlet`) memvalidasi terhadap `config('mobile.radius_outlet_m')`, default **100 meter** (`MOBILE_RADIUS_OUTLET_M`, [config/mobile.php:21](../config/mobile.php)) — bukan 5 meter. Perlu diputuskan: ubah konfigurasi jadi 5 m, atau 5 m di sini maksudnya toleransi akurasi GPS yang berbeda dari radius batas Check In.
- _(TBD: status NOO/AC/EC/RO/OSS dan kapan outlet pindah status, aturan Join Visit, outlet di luar rute)_

## 6. Penjualan (transaksi canvassing)

_Aturan harga, diskon, retur saat transaksi, syarat outlet boleh dijual (status, utang, dll.), effective call._

- **Dikonfirmasi** (2026-10-30, klarifikasi product owner) — Transaksi penjualan hanya boleh dibuat dalam kunjungan outlet yang sudah Check In dan belum Check Out; setelah transaksi di-submit, motoris melakukan Check Out untuk menutup kunjungan. — _lihat §5; kode: `App\Http\Controllers\Mobile\KunjunganController::terbuka()`_
- _(TBD: aturan harga, diskon, retur saat transaksi, syarat outlet boleh dijual, effective call)_

## 7. Stok: outlet, kompetitor, retur

_Pencatatan stok outlet & kompetitor saat visit, aturan retur barang canvassing._

- _(TBD)_

## 8. Setoran & setor margin

_Aturan setoran hasil penjualan, setor margin ke depo, tenggat, selisih/kekurangan setoran._

- _(TBD)_

## 9. Target & insentif

_Rumus insentif per depo/produk, cara target ditetapkan, konversi poin, periode perhitungan._

- **Belum diketahui** — rumus insentif dan potongan di Marketama hanya tampak sebagai data referensi (CSV), logika perhitungannya belum teramati. — _docs/PRD.md §5_
- _(TBD setelah penelusuran alur tulis Absensi/Penggajian — lihat PRD §7 fase 7)_

## 10. Izin & potongan

_Jenis izin, alur persetujuan, jenis potongan gaji dan pemicunya._

- _(TBD)_

## 11. Penggajian

_Komponen gaji, periode, hubungan dengan absensi/insentif/potongan, siapa yang mem-finalisasi._

- **Catatan proses** — hasil gaji & insentif aplikasi baru harus identik dengan Marketama (toleransi disepakati) untuk minimal satu periode penuh sebelum cut-over; pemilik proses/validasi: Super Admin PT. Marketama. — _docs/PRD.md §9/§10_
- _(TBD)_

## 12. Outlet: status, duplikat & fiktif

_Kriteria outlet dianggap duplikat atau fiktif, siapa yang menandai/memvalidasi, dampaknya ke rute & penjualan._

- **Dikonfirmasi** (2026-10-30, klarifikasi product owner) — NOO (outlet baru, `trx_outlet_baru`) yang didaftarkan motoris lewat HP harus **divalidasi oleh Koordinator** (setuju/tolak) sebelum masuk ke master outlet (`mk_outlet`).
  - ⚠️ **Perlu ditinjau** — implementasi saat ini (`App\Http\Controllers\OutletBaruController::setujui/tolak`, dipakai dari menu web "All Outlet" tab "Outlet Baru dari HP") tidak mengecek peran Koordinator secara spesifik; siapa pun akun web dengan **cakupan akses** ke sales tersebut (`App\Support\Akses\Akses::batasiKaryawan` — depo/downline/diri sendiri) dan hak ke menu itu bisa menyetujui/menolak. Tidak ada endpoint di aplikasi HP (`App\Http\Controllers\Mobile\KoordinatorController`) untuk Koordinator melakukan ini dari HP-nya sendiri. Perlu diputuskan: apakah validasi NOO cukup dibatasi lewat hak menu web (role "Koordinator" di tabel role), atau perlu ditambah alur approval di aplikasi HP koordinator.
- _(TBD: kriteria outlet dianggap duplikat/fiktif dan siapa yang menandainya — beda dari validasi NOO di atas)_

## 13. Notifikasi & eskalasi

_Kapan sistem/koordinator diberi tahu (absen telat, outlet fiktif, setoran kurang, dll.)._

- _(TBD)_

---

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

Catat di sini hanya perubahan **aturan bisnis** (bukan refactor kode), terutama yang membatalkan keputusan sebelumnya, supaya tidak ada dua sumber yang saling kontradiksi.

| Tanggal | Aturan yang berubah | Dari → Ke | Alasan | Sumber |
| --- | --- | --- | --- | --- |
| 2026-10-28 | Validasi lokasi saat absen sales/koordinator | Radius depo → Area kerja (polygon, point-in-polygon) | Sales sudah di lapangan saat jam absen, bisa jauh dari depo; checkin depo (ambil barang) tetap soal hadir fisik di gudang | spec 035 (membatalkan spec 033, selisih keputusan 12 menit) |
| 2026-10-30 | Istilah & lokasi Absen vs Check In | "Checkin Depo"/"Absen" dianggap dua hal tak jelas lokasinya → **Absen Datang/Pulang terjadi di Depo**; **Check In hanya untuk Outlet**. Membatalkan sebagian spec 035 (yang memvalidasi absen terhadap area kerja, bukan depo) | Klarifikasi product owner: istilah operasional yang benar | Percakapan klarifikasi 2026-10-30 — implementasi kode (`attendances/check-in`, `WilayahKerja`) **belum disesuaikan**, lihat §3 |
