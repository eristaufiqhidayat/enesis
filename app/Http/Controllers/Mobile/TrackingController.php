<?php

namespace App\Http\Controllers\Mobile;

use App\Support\Mobile\ApiError;
use App\Support\Mobile\TokenPerangkat;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Kumpulan titik GPS dari HP. Titik dengan waktu sama untuk karyawan yang sama diabaikan,
// jadi paket boleh dikirim ulang utuh; titik lebih tua dari 48 jam ditolak per titik.
class TrackingController extends MobileController
{
    public function batch(Request $request): JsonResponse
    {
        $maks = (int) config('mobile.tracking_maks_titik');
        $titik = $request->input('points');

        if (is_array($titik) && count($titik) > $maks) {
            throw new ApiError('too_many_points', "Maksimal $maks titik per kiriman.", 422);
        }

        $request->validate([
            'points' => ['required', 'array', 'min:1'],
            'points.*.recorded_at' => ['required', 'date'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'points.*.is_mock_location' => ['nullable', 'boolean'],
            'points.*.battery' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $karyawanId = (int) $this->karyawan($request)->id;
        $sekarang = now();
        $batasUmur = $sekarang->copy()->subHours((int) config('mobile.tracking_maks_umur_jam'));
        $batasAkurasi = (int) config('mobile.tracking_akurasi_ditandai_m');
        $device = TokenPerangkat::token($request)->device_id ?? null;
        $baris = [];
        $ditolak = [];

        foreach ($titik as $i => $t) {
            $waktu = CarbonImmutable::parse($t['recorded_at'])->utc();

            if ($waktu->lessThan($batasUmur) || $waktu->greaterThan($sekarang->copy()->addMinutes(10))) {
                $ditolak[] = ['index' => $i, 'code' => $waktu->lessThan($batasUmur) ? 'too_old' : 'future_time'];

                continue;
            }

            $akurasi = isset($t['accuracy_m']) ? (int) round((float) $t['accuracy_m']) : null;
            $kunci = $waktu->format('Y-m-d H:i:s');
            $baris[$kunci] = [
                'karyawan_id' => $karyawanId,
                'recorded_at' => $kunci,
                'lat' => $t['lat'],
                'lng' => $t['lng'],
                'akurasi_m' => $akurasi,
                'akurasi_rendah' => $akurasi !== null && $akurasi > $batasAkurasi,
                'mock_location' => (bool) ($t['is_mock_location'] ?? false),
                'baterai' => $t['battery'] ?? null,
                'device_id' => $device,
                'received_at' => $sekarang,
            ];
        }

        $masuk = 0;

        foreach (array_chunk(array_values($baris), 250) as $potong) {
            $masuk += DB::table('trx_tracking')->insertOrIgnore($potong);
        }

        return $this->ok([
            'accepted' => $masuk,
            'duplicates' => count($titik) - count($ditolak) - $masuk,
            'rejected' => $ditolak,
        ]);
    }
}
