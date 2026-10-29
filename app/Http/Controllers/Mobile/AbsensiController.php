<?php

namespace App\Http\Controllers\Mobile;

use App\Support\Mobile\ApiError;
use App\Support\Mobile\HariKerja;
use App\Support\Mobile\Pengaturan;
use App\Support\Mobile\WilayahKerja;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Absen masuk/pulang dan checkin/checkout depo — TERPISAH (keputusan 28 Okt 2026, lihat
// claude/menu-absensi-master-area.md): absen dicek terhadap area kerja (Master Area; sales = areanya
// sendiri, koordinator = gabungan area aktif tim), checkin/checkout depo dicek terhadap radius depo
// untuk ambil & kembalikan barang canvassing. Dua aksi ini TIDAK saling membuat atau menutup satu sama
// lain — mobile memanggil keduanya terpisah (AbsenScreen vs CheckinDepoScreen, TutupHariScreen memanggil
// depot-visits/check-out lalu attendances/check-out). (PR #115 "satukan absen+checkin depo" — di-merge
// 28 Okt 23:00 tanpa diketahui keputusan ini diambil 22:48 hari yang sama — dibatalkan oleh perubahan ini.)
class AbsensiController extends MobileController
{
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate($this->aturanUmum() + $this->aturanLokasi() + [
            'photo_id' => array_merge(['required'], $this->aturanUpload($request)),
        ], ['photo_id.required' => 'Foto selfie wajib.']);

        $karyawan = $this->karyawan($request);
        $sekarang = $this->sekarang();
        $tanggal = $sekarang->toDateString();
        $jam = Pengaturan::jamAktif($sekarang, $request->user()->kategori);
        $libur = Pengaturan::libur($tanggal, $tanggal)[$tanggal] ?? null;

        if ($libur || ! $jam['hari_kerja']) {
            throw ApiError::konflik('holiday', $libur ? "Hari ini libur: $libur." : 'Hari ini bukan hari kerja aktif.');
        }

        $pukul = $sekarang->format('H:i');

        if ($pukul < $jam['mulai'] || $pukul > $jam['selesai']) {
            throw ApiError::konflik('outside_active_hours', "Absen hanya bisa pukul {$jam['mulai']}–{$jam['selesai']}.");
        }

        if ($ada = $this->hari($request, $tanggal)->absensi()) {
            throw ApiError::konflik('already_checked_in', 'Anda sudah absen masuk hari ini.', ['attendance_id' => (int) $ada->id]);
        }

        if (config('mobile.absen_cek_lokasi')) {
            $areaKerja = WilayahKerja::untukAkun($request->user()->kategori, (int) $karyawan->id);

            if (! $areaKerja) {
                throw new ApiError('no_work_area', 'Anda belum punya area kerja aktif. Hubungi admin.', 409);
            }

            if (! WilayahKerja::titikDalam((float) $request->lat, (float) $request->lng, $areaKerja)) {
                throw new ApiError('outside_work_area', 'Anda di luar area kerja.', 422, [
                    'work_areas' => array_map(fn ($a) => $a['name'], $areaKerja),
                ]);
            }
        }

        $terlambat = $pukul > $jam['batas_masuk'];
        $umum = $this->kolomUmum($request);
        $masuk = now();

        $id = DB::table('trx_absensi')->insertGetId([
            'karyawan_id' => $karyawan->id,
            'depo_id' => $karyawan->depo_id,
            'tanggal' => $tanggal,
            'masuk_at' => $masuk,
            'masuk_lat' => $request->lat,
            'masuk_lng' => $request->lng,
            'masuk_akurasi_m' => $request->filled('accuracy_m') ? (int) round((float) $request->accuracy_m) : null,
            'masuk_foto_id' => $request->photo_id,
            'terlambat' => $terlambat,
            'mock_location' => $request->boolean('is_mock_location'),
        ] + $umum);

        return $this->dibuat([
            'attendance_id' => $id,
            'date' => $tanggal,
            'checked_in_at' => $sekarang->toIso8601String(),
            'is_late' => $terlambat,
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $request->validate($this->aturanUmum() + $this->aturanLokasi(false));

        $hari = $this->hari($request);
        $absen = $hari->wajibAbsen();

        if ($absen->pulang_at) {
            throw ApiError::konflik('already_checked_out', 'Anda sudah absen pulang hari ini.');
        }

        $this->tutupHari($hari);
        $pulang = now();

        DB::table('trx_absensi')->where('id', $absen->id)->update([
            'pulang_at' => $pulang,
            'pulang_lat' => $request->lat,
            'pulang_lng' => $request->lng,
            'pulang_client_ref' => $request->client_ref,
        ]);

        return $this->ok(['attendance_id' => (int) $absen->id, 'checked_out_at' => $this->sekarang()->toIso8601String()]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $bulan = $request->input('month', $this->sekarang()->format('Y-m'));
        $awal = CarbonImmutable::parse("$bulan-01");

        $baris = DB::table('trx_absensi')->where('karyawan_id', $this->karyawan($request)->id)
            ->whereBetween('tanggal', [$awal->toDateString(), $awal->endOfMonth()->toDateString()])
            ->orderBy('tanggal')->get()
            ->map(fn ($a) => [
                'id' => (int) $a->id,
                'date' => substr((string) $a->tanggal, 0, 10),
                'checked_in_at' => $this->waktu($a->masuk_at),
                'checked_out_at' => $this->waktu($a->pulang_at),
                'is_late' => (bool) $a->terlambat,
            ]);

        return $this->ok($baris, ['month' => $bulan]);
    }

    public function depoCheckIn(Request $request): JsonResponse
    {
        $request->validate($this->aturanUmum() + $this->aturanLokasi());

        $karyawan = $this->karyawan($request);
        $hari = $this->hari($request);
        $hari->wajibAbsen();

        if ($ada = $hari->checkinDepo()) {
            throw ApiError::konflik('already_checked_in', 'Anda sudah checkin depo hari ini.', ['depot_visit_id' => (int) $ada->id]);
        }

        $depo = Pengaturan::depo($karyawan->depo_id ? (int) $karyawan->depo_id : null)
            ?? throw new ApiError('no_depot', 'Akun belum punya depo. Hubungi admin.', 409);
        $jarak = $this->cekRadiusDepo($depo, (float) $request->lat, (float) $request->lng);

        $id = DB::table('trx_checkin_depo')->insertGetId([
            'karyawan_id' => $karyawan->id,
            'depo_id' => $depo['id'],
            'tanggal' => $hari->tanggal,
            'checkin_at' => now(),
            'lat' => $request->lat,
            'lng' => $request->lng,
            'jarak_m' => $jarak,
        ] + $this->kolomUmum($request));

        return $this->dibuat(['depot_visit_id' => $id, 'distance_to_depot_m' => $jarak]);
    }

    public function depoCheckOut(Request $request): JsonResponse
    {
        $request->validate($this->aturanUmum() + $this->aturanLokasi(false));

        $hari = $this->hari($request);
        $checkin = $hari->checkinDepo() ?? throw ApiError::konflik('not_checked_in', 'Anda belum checkin depo hari ini.');

        if ($checkin->checkout_at) {
            throw ApiError::konflik('already_checked_out', 'Anda sudah checkout depo hari ini.');
        }

        $this->tutupHari($hari);
        $pulang = now();

        DB::table('trx_checkin_depo')->where('id', $checkin->id)->update([
            'checkout_at' => $pulang,
            'checkout_client_ref' => $request->client_ref,
        ]);

        return $this->ok(['depot_visit_id' => (int) $checkin->id, 'checked_out_at' => $this->sekarang()->toIso8601String()]);
    }

    /** Syarat tutup hari, berlaku untuk absen pulang maupun checkout depo (dipanggil terpisah dari dua-duanya): tidak ada kunjungan / join visit terbuka, sisa canvassing sudah diserahkan. */
    private function tutupHari(HariKerja $hari): void
    {
        $hari->tanpaKunjunganTerbuka();

        if ($jv = KoordinatorController::terbuka((int) $hari->karyawanId)) {
            throw ApiError::konflik('join_visit_open', 'Selesaikan join visit dulu sebelum absen pulang.', ['join_visit_id' => (int) $jv->id]);
        }

        $canvassing = $hari->canvassingDiterima();

        if ($canvassing && ! $canvassing->dikembalikan_at) {
            throw ApiError::konflik('return_pending', 'Serahkan sisa barang canvassing dulu sebelum absen pulang / checkout depo.');
        }
    }
}
