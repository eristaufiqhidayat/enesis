# Master Wilayah & Batas dari Sumber Resmi Pemerintah

## Problem Statement

Master wilayah Enesis (`provinsi`, `kota_kabupaten`, `kecamatan`, `kelurahan`) adalah salinan Marketama. Hasil analisa (project doc `claude/menu-master-*.md`):

- Bagian berkode sebagian besar sesuai Kemendagri, tetapi **belum lengkap** (81.870 kelurahan vs 83.762 di Kemendagri 2025) dan ada yang **salah**: kecamatan Kutai Barat tercatat di Kab. Mahakam Ulu, 215 kecamatan berbeda isi, pembagian kabupaten di 6 provinsi Papua tidak sesuai pemekaran 2022, "Kabupaten Poso Pesisir" yang sebenarnya kecamatan, dan nama berbahasa Inggris dobel.
- Ada **576 kecamatan dan 2.221 kelurahan tambahan tanpa kode**, dibuat Marketama dari alamat outlet ("East Pejaten", "Rt.24", "BSD City").
- Batas kelurahan (PR #89) diambil dari dataset komunitas cahyadsn/wilayah_boundaries, bukan langsung dari layanan pemerintah.

## Solution

### 1. Daftar wilayah: API relasi kode BPS–Kemendagri

- Sumber: layanan **Kode Relasi BPS–Kemendagri** milik BPS (`https://sig.bps.go.id/rest-bridging/getwilayah?level=…&parent=…`). Tiap baris memuat `kode_bps`, `nama_bps`, `kode_dagri`, `nama_dagri`; Enesis memakai kode dan nama Kemendagri.
  - Kemendagri tidak menyediakan API publik; relasi BPS inilah API pemerintah yang memuat kode resmi Kemendagri sampai desa.
- `App\Support\Wilayah\WilayahBps`: permintaan paralel (`WILAYAH_BPS_PARALEL`, bawaan 4), retry, dan **cache berkas** jawaban di `storage/app/private/wilayah-resmi/bps/<periode>/`. Periode relasi bisa dipilih lewat `WILAYAH_BPS_PERIODE` (parameter `periode_merge`).
  - Tingkat desa butuh ±7.300 permintaan (satu per kecamatan); cache membuat putaran ulang cepat dan bisa dilanjutkan bila terputus.
- `App\Support\Wilayah\SinkronWilayah` mencocokkan lewat **kode Kemendagri**, tingkat demi tingkat, dengan status per baris:

| Status | Arti | Dengan `--simpan` | Dengan `--perbaiki` |
|---|---|---|---|
| cocok | kode, nama, induk sama | `sumber = resmi`, isi `kode_bps` | — |
| beda_nama | nama berbeda (bukan sekadar gaya tulis "KAB." / huruf besar) | `sumber = resmi` | nama diganti |
| beda_induk | tercatat di bawah induk lain | `sumber = resmi` | induk dipindah |
| baru | ada di resmi, belum di Enesis | baris ditambah (`source_id` kosong) | |
| tidak_resmi | kode Enesis tidak ada di data resmi | `sumber = tidak_resmi` | tidak dihapus |
| tambahan | tanpa kode | `sumber = tambahan` | tidak disentuh |
| tidak_dicek | induknya gagal diambil dari API | tidak diubah | |

- Induk dinilai lewat kode: baris benar bila baris induk yang namanya dirujuk berkode sama dengan awalan kodenya sendiri. Penilaian ini tidak terpengaruh perubahan nama di tingkat atas.
- Relasi master masih lewat **nama**. Ganti nama/induk ikut memperbarui kolom nama di anak-anaknya: anak berkode hanya bila awalan kodenya sesuai, anak tanpa kode selalu ikut.
- **ID tidak pernah berubah dan tidak ada baris yang dihapus**, karena outlet, area, dan depo merujuk master ini.
- Nama baru dirapikan ke gaya master (`App\Support\Wilayah\NamaWilayah`): "KAB. ADM. KEP. SERIBU" → "Kabupaten Administrasi Kepulauan Seribu", "SIANTAR MARIHAT II" → "Siantar Marihat II".
- Migration menambah `kode_bps`, `sumber`, `sinkron_at` dan index `kode` di keempat tabel.
- Command:

```bash
php artisan wilayah:sinkron-resmi                       # laporan saja (CSV), tidak menulis
php artisan wilayah:sinkron-resmi --provinsi=32 --sampai=kecamatan
php artisan wilayah:sinkron-resmi --simpan              # tandai sumber + tambah wilayah resmi yang belum ada
php artisan wilayah:sinkron-resmi --simpan --perbaiki   # + ganti nama/induk yang berbeda
php artisan wilayah:sinkron-resmi --segarkan            # abaikan cache, ambil ulang dari BPS
```

Laporan CSV (`storage/app/private/wilayah-resmi/laporan/`) berisi tingkat, status, id, kode, kode BPS, nama & induk di Enesis, dan nama & induk resmi untuk semua baris selain `cocok`.

### 2. Batas kelurahan: layanan peta BIG

- Sumber utama baru: **Badan Informasi Geospasial**, `geoservices.big.go.id/rbi/rest/services/BATASWILAYAH/Administrasi_AR_KelDesa_10K/MapServer/0` ("Batas Wilayah Kelurahan/Desa", skala 1:10.000, ArcGIS REST, GeoJSON, WGS84).
- `App\Support\Wilayah\BatasBig` mengambil per kota: `where KDPKAB='32.01'`, paging `resultOffset`/`resultRecordCount` (`BATAS_WILAYAH_BIG_HALAMAN`, bawaan 100), `geometryPrecision=6`, opsional `maxAllowableOffset` (`BATAS_WILAYAH_BIG_SEDERHANAKAN`). Fitur satu desa (pulau terpisah) digabung jadi satu MultiPolygon per `KDEPUM`. Galat ArcGIS (HTTP 200 + `error`) dikenali.
- `BatasWilayah::pastikanKota` memilih sumber (`BATAS_WILAYAH_SUMBER` = `big` | `kemendagri-2025`). Kelurahan master yang tidak ada di BIG (mis. desa pemekaran baru), atau semua bila BIG gagal, diisi dari **cadangan** (`BATAS_WILAYAH_CADANGAN`, bawaan dataset Kemendagri 2025). `batas_wilayah.sumber` mencatat asal tiap batas (`big-keldesa-10k` / `kemendagri-2025`).
- `wilayah:impor-batas` mendapat `--sumber` dan `--tanpa-cadangan`, dan tabel ringkasannya per sumber.

## Urutan di server

1. `php artisan migrate`
2. `php artisan wilayah:sinkron-resmi` → periksa laporan CSV (terutama `beda_induk` di Papua dan `tidak_resmi`).
3. `php artisan wilayah:sinkron-resmi --simpan` (aman: hanya menandai dan menambah). Bila laporan sudah disetujui admin: `--simpan --perbaiki`.
4. `php artisan wilayah:impor-batas --semua --paksa` untuk mengganti batas yang sudah diambil dari dataset dengan batas BIG (opsional; kota yang belum pernah dibuka akan memakai BIG otomatis).

## Di luar cakupan

- Merapikan baris `tambahan` (dobel, nama Inggris, RT/RW) dan memindahkan rujukan outlet/area/depo ke baris resmi. Kolom `sumber` dan laporan CSV menjadi bahan untuk admin.
- Mengubah kolom nama wilayah di `area_polygon`, `mk_outlet`, `master_depo` saat master diganti nama.
- Menu web untuk menjalankan sinkron (cukup command).
- Batas tingkat kota/kecamatan.

## Testing

- `tests/Feature/SinkronWilayahResmiTest.php` (API BPS di-fake): laporan tanpa menulis; `--simpan` menandai sumber/kode BPS dan menambah kecamatan + desa baru dengan nama rapi; `--perbaiki` mengganti nama dan memindah induk (kasus Kutai Barat) dengan anak ikut; gaya tulis berbeda tetap `cocok`; induk gagal diambil → `tidak_dicek`; filter provinsi membatasi permintaan; cache dipakai ulang.
- `tests/Feature/BatasWilayahBigTest.php` (BIG & GitHub di-fake): fitur digabung per kode, paging berhenti di halaman terakhir, fitur kota lain diabaikan, kode yang tidak ada di BIG diisi cadangan, galat ArcGIS jatuh ke cadangan, tanpa cadangan galat dilaporkan, ringkasan command per sumber.
