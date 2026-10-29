# Pisahkan absen dari checkin depo; absen dicek terhadap Master Area

## Problem Statement
PR #033 ("Satukan absen dan checkin depo seperti Marketama") di-merge 28 Okt 2026 pukul 23:00 WIB. Sore harinya (22:33–22:55 WIB) product owner sudah memutuskan sebaliknya untuk aplikasi HP: absen sales motoris dan koordinator tidak lagi divalidasi terhadap radius depo, melainkan terhadap **Master Area** (polygon wilayah kerja) — karena sales bisa jauh dari depo saat jam absen (sudah di lapangan), dan sebaliknya checkin depo (ambil barang canvassing) tetap soal hadir fisik di gudang. PR #033 ter-merge tanpa sepengetahuan keputusan ini (selisih 12 menit), sehingga kontrak API & aplikasi HP sempat tidak sinkron satu sama lain. Keputusan ini membatalkan arah #033 untuk aplikasi mobile.

## Solution
- **Absen** (`POST /attendances/check-in`) divalidasi terhadap **area kerja**, bukan radius depo:
  - Sales: area aktif miliknya sendiri (`mk_karyawan_area.aktif`, atau semua areanya bila tidak ada yang aktif — `HariKerja::areaKaryawan()`).
  - Koordinator: gabungan (union) area aktif seluruh sales bawahannya (`mk_karyawan.koordinator_id`).
  - Titik GPS harus berada **di dalam polygon** salah satu area tersebut (point-in-polygon, bukan jarak/radius).
  - Tidak ada fallback ke radius depo: akun tanpa area kerja aktif (`work_areas` kosong) → absen ditolak `409 no_work_area`. Di luar semua polygon → `422 outside_work_area`.
  - Diaktifkan lewat `mobile.absen_cek_lokasi` (env `MOBILE_ABSEN_CEK_LOKASI`, ganti nama dari `absen_cek_radius`/`MOBILE_ABSEN_CEK_RADIUS` — makna sekarang "area", bukan "radius").
- **Checkin/checkout depo** (`POST /depot-visits/check-in|check-out`) kembali **berdiri sendiri**, tidak lagi dibuat/ditutup otomatis oleh absen:
  - Tetap pakai radius depo (`mobile.radius_depo_m`), tidak berubah.
  - `attendances/check-in` tidak lagi mengembalikan `depot_visit_id` / `distance_to_depot_m`, dan tidak lagi membuat baris `trx_checkin_depo`.
  - `attendances/check-out` tidak lagi menutup `trx_checkin_depo.checkout_at`, dan sebaliknya.
  - Checkin depo kedua kali di hari yang sama kini `409 already_checked_in` (sebelumnya diam-diam sukses karena dianggap "sudah tercatat lewat absen").
- **`GET /config`** menambah field `work_areas`: list `{id, name, polygon: [{lat, lng}, ...]}` — area aktif akun yang login (sales) atau gabungan tim (koordinator). Polygon = cincin terluar terluas saja (tanpa lubang, tanpa bagian terpisah) — penyederhanaan yang sama dengan `area_polygon.polygon` (format lama).
- **`App\Support\Mobile\WilayahKerja`** (baru): menghitung `work_areas` dan melakukan point-in-polygon server-side, dengan geometri & algoritma ray-casting **persis sama** dengan `lib/core/geo.dart` di aplikasi HP (lihat enesis-mobile PR terkait) — supaya titik yang dianggap "di dalam area" di HP tidak pernah berbeda dengan keputusan server. Menggunakan ulang `App\Support\Area\WilayahKerjaSales::polygon()/multi()` (dibuat `public`) untuk resolusi `mk_area` → `area_polygon`, bukan query baru.
- Migration `2026_10_28_000003_satukan_absen_checkin_depo.php` (pelengkap data historis) **tidak diubah** — tetap berguna untuk mencocokkan absen lama dengan checkin depo lama, lepas dari perubahan perilaku ke depan.

## Lanjutan
- **enesis-mobile**: layar "Absen & checkin depo" dipecah jadi `AbsenScreen` (area kerja + selfie) dan `CheckinDepoScreen` (radius depo, terpisah) — lihat `claude/menu-absensi-master-area.md` di project Enesis dan PR terkait di `eristaufiqhidayat/enesis-mobile`.
- Belum ditangani (lihat juga catatan "belum dikonfirmasi" di `claude/menu-absensi-master-area.md`):
  - Toleransi tepi polygon (buffer beberapa meter) untuk akurasi GPS yang meleset.
  - Area tanpa `polygon`/`geometri` di `area_polygon` (mis. baru ditambahkan admin, belum digambar) otomatis dilewati dari `work_areas` — sales/koordinator yang areanya seperti ini perlu area lain yang sudah berpolygon, atau akan selalu ditolak absen.
  - Performa: `WilayahKerja` belum di-index/cache; untuk koordinator dengan tim besar query `mk_karyawan_area` dipanggil per sales (N+1 kecil, belum masalah pada skala saat ini tapi layak dipantau).

## Testing
- `tests/Feature/Mobile/AbsenCheckinDepoTest.php`: ditulis ulang — absen tidak membuat checkin depo, absen ditolak di luar area kerja (meski dekat depo), absen ditolak tanpa area kerja aktif, absen koordinator dicek gabungan area tim, checkin depo berdiri sendiri dan tidak memengaruhi absen. Test migrasi data lama dipertahankan apa adanya.
- `HariKerjaSalesTest::test_satu_hari_kerja_sales_motoris`: langkah 2 (checkin depo) dan langkah 5 (tutup hari) disesuaikan jadi dua panggilan API independen; radius-depo-saat-absen diganti area-kerja-saat-absen.
- `MobileApiTestCase::isiMaster()`: tambah baris `area_polygon` (source_id 10) berupa kotak yang mencakup `diDepo()`/`diOutlet(11)`/`diOutlet(12)` tapi tidak titik -6.21,106.8 ke bawah, dipakai tes "di luar area".
- `KoordinatorMobileTest` tidak berubah — `diDepo()` kebetulan tetap di dalam area gabungan tim (area 10, dipakai sales 1 & 2 di bawah koordinator 137).
- `docs/api/mobile-v1.yaml` diperbarui: `work_areas` di `/config`, kode error `no_work_area`/`outside_work_area`, deskripsi endpoint absen & checkin/checkout depo.
