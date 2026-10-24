<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Support\Mobile\ApiError;
use App\Support\Mobile\HariKerja;
use App\Support\Mobile\Lokasi;
use App\Support\Mobile\Pengaturan;
use App\Support\Mobile\TokenPerangkat;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// Dasar controller API mobile: bentuk respons {"data", "meta"}, data karyawan yang login,
// dan kolom umum transaksi (client_ref, recorded_at, received_at, clock_skew, device_id).
abstract class MobileController extends Controller
{
    protected function ok(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => (object) $meta], $status);
    }

    protected function dibuat(mixed $data, array $meta = []): JsonResponse
    {
        return $this->ok($data, $meta, 201);
    }

    /** Baris mk_karyawan milik akun yang login (dijamin ada oleh middleware mobile.peran). */
    protected function karyawan(Request $request): object
    {
        return $request->attributes->get('mobile_karyawan')
            ?? tap(DB::table('mk_karyawan')->where('id', $request->user()->karyawan_id)->first()
                ?? throw new ApiError('no_employee_link', 'Data karyawan untuk akun ini tidak ditemukan. Hubungi admin.', 403),
                fn ($k) => $request->attributes->set('mobile_karyawan', $k));
    }

    protected function hari(Request $request, ?string $tanggal = null): HariKerja
    {
        return new HariKerja((int) $this->karyawan($request)->id, $tanggal ?? $this->hariIni());
    }

    /** Jarak ke depo (meter); lewat radius => outside_depot_radius. Depo tanpa koordinat tidak diperiksa. */
    protected function cekRadiusDepo(?array $depo, float $lat, float $lng): ?int
    {
        if (! $depo || $depo['lat'] === null) {
            return null;
        }

        $jarak = Lokasi::jarak($lat, $lng, $depo['lat'], $depo['lng']);
        $batas = (int) config('mobile.radius_depo_m');

        if ($jarak > $batas) {
            throw new ApiError('outside_depot_radius', "Anda $jarak m dari depo, batas $batas m.", 422, ['distance_m' => $jarak, 'radius_m' => $batas]);
        }

        return $jarak;
    }

    /** Aturan lokasi GPS dari HP. */
    protected function aturanLokasi(bool $wajib = true): array
    {
        $r = $wajib ? 'required' : 'nullable';

        return [
            'lat' => [$r, 'numeric', 'between:-90,90'],
            'lng' => [$r, 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'is_mock_location' => ['nullable', 'boolean'],
        ];
    }

    protected function sekarang(): CarbonImmutable
    {
        return Pengaturan::sekarang();
    }

    protected function hariIni(): string
    {
        return $this->sekarang()->toDateString();
    }

    /** Aturan validasi kolom umum transaksi. */
    protected function aturanUmum(): array
    {
        return [
            'client_ref' => ['required', 'uuid'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }

    /** Kolom umum transaksi untuk disimpan. */
    protected function kolomUmum(Request $request, ?string $kolomRef = 'client_ref'): array
    {
        $diterima = now();
        $dicatat = $request->filled('recorded_at') ? CarbonImmutable::parse($request->input('recorded_at'))->utc() : null;
        $selisih = $dicatat ? abs($diterima->diffInMinutes($dicatat)) : 0;
        $token = TokenPerangkat::token($request);

        $kolom = [
            'recorded_at' => $dicatat ?? $diterima,
            'received_at' => $diterima,
            'clock_skew' => $selisih > config('mobile.toleransi_jam_menit'),
            'device_id' => $token->device_id ?? null,
            'app_version' => $request->header('X-App-Version') ?? ($token->app_version ?? null),
        ];

        if ($kolomRef) {
            $kolom[$kolomRef] = $request->input('client_ref');
        }

        return $kolom;
    }

    /** Aturan: upload_id milik user ini (opsional jenis tertentu). */
    protected function aturanUpload(Request $request): array
    {
        return ['uuid', Rule::exists('mobile_uploads', 'id')->where('user_id', $request->user()->id)];
    }

    protected function waktu(mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        $waktu = $nilai instanceof CarbonInterface ? $nilai : CarbonImmutable::parse($nilai, 'UTC');

        return $waktu->setTimezone(Pengaturan::zona())->toIso8601String();
    }

    protected function koordinat(mixed $nilai): ?float
    {
        return $nilai === null ? null : round((float) $nilai, 6);
    }

    protected function qty(mixed $nilai): float
    {
        return round((float) $nilai, 2);
    }
}
