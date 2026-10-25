<?php

namespace App\Http\Controllers\Mobile;

use App\Support\Mobile\ApiError;
use App\Support\Mobile\Lokasi;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

// Aplikasi HP koordinator: tim hari ini, outlet terdekat, dan join visit (mendampingi sales ke outlet).
// Absen dan tracking GPS koordinator memakai endpoint yang sama dengan sales (trx_absensi, trx_tracking).
class KoordinatorController extends MobileController
{
    /** Sales bawahan (mk_karyawan.koordinator_id) beserta kegiatan hari ini. */
    public function tim(Request $request): JsonResponse
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $tanggal = $request->input('date', $this->hariIni());
        $sales = $this->queryTim($request)->orderBy('nama')->get(['id', 'nama', 'no_hp', 'depo_id']);
        $ids = $sales->pluck('id');

        $absen = DB::table('trx_absensi')->whereIn('karyawan_id', $ids)->where('tanggal', $tanggal)->get()->keyBy('karyawan_id');
        $kunjungan = DB::table('trx_kunjungan')->whereIn('karyawan_id', $ids)->where('tanggal', $tanggal)
            ->selectRaw('karyawan_id, COUNT(*) AS jumlah, SUM(CASE WHEN checkout_at IS NULL THEN 1 ELSE 0 END) AS terbuka')
            ->groupBy('karyawan_id')->get()->keyBy('karyawan_id');
        $jual = DB::table('trx_penjualan')->whereIn('karyawan_id', $ids)->where('tanggal', $tanggal)->whereNull('dibatalkan_at')
            ->selectRaw('karyawan_id, COUNT(*) AS nota, SUM(total) AS total, SUM(CASE WHEN is_effective_call THEN 1 ELSE 0 END) AS ec')
            ->groupBy('karyawan_id')->get()->keyBy('karyawan_id');
        $posisi = $this->posisiTerakhir($ids);
        $depo = DB::table('mk_depo')->whereIn('id', $sales->pluck('depo_id')->filter())->pluck('nama', 'id');

        $data = $sales->map(fn ($s) => [
            'id' => (int) $s->id,
            'name' => $s->nama,
            'phone' => $s->no_hp,
            'depot' => $depo[$s->depo_id] ?? null,
            'attendance' => ($a = $absen[$s->id] ?? null) ? [
                'checked_in_at' => $this->waktu($a->masuk_at),
                'checked_out_at' => $this->waktu($a->pulang_at),
                'is_late' => (bool) $a->terlambat,
            ] : null,
            'visits' => (int) ($kunjungan[$s->id]->jumlah ?? 0),
            'visit_open' => (int) ($kunjungan[$s->id]->terbuka ?? 0) > 0,
            'invoices' => (int) ($jual[$s->id]->nota ?? 0),
            'ec' => (int) ($jual[$s->id]->ec ?? 0),
            'sales_amount' => (int) ($jual[$s->id]->total ?? 0),
            'last_position' => $posisi[$s->id] ?? null,
        ])->values();

        return $this->ok($data, [
            'date' => $tanggal,
            'total' => $data->count(),
            'checked_in' => $data->whereNotNull('attendance')->count(),
            'visits' => $data->sum('visits'),
            'sales_amount' => $data->sum('sales_amount'),
        ]);
    }

    /** Outlet master terdekat dari posisi koordinator (untuk memulai join visit). */
    public function outletTerdekat(Request $request): JsonResponse
    {
        $request->validate($this->aturanLokasi() + [
            'radius' => ['nullable', 'integer', 'min:10', 'max:2000'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $radius = (int) $request->input('radius', 300);
        [$latMin, $latMax, $lngMin, $lngMax] = Lokasi::kotak($lat, $lng, $radius);

        $outlet = DB::table('mk_outlet as o')->leftJoin('mk_area as a', 'a.id', '=', 'o.area_id')
            ->whereBetween('o.latitude', [$latMin, $latMax])->whereBetween('o.longitude', [$lngMin, $lngMax])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw('LOWER(o.nama) LIKE ?', ['%'.mb_strtolower($request->q).'%'])
                ->orWhereRaw('LOWER(o.kode) LIKE ?', ['%'.mb_strtolower($request->q).'%'])))
            ->limit(500)->get(['o.id', 'o.kode', 'o.nama', 'o.alamat', 'o.latitude', 'o.longitude', 'o.area_id', 'a.nama as area'])
            ->map(fn ($o) => [
                'id' => (int) $o->id,
                'code' => $o->kode,
                'name' => $o->nama,
                'address' => $o->alamat,
                'area' => $o->area,
                'lat' => $this->koordinat($o->latitude),
                'lng' => $this->koordinat($o->longitude),
                'distance_m' => Lokasi::jarak($lat, $lng, (float) $o->latitude, (float) $o->longitude),
            ])
            ->filter(fn ($o) => $o['distance_m'] <= $radius)->sortBy('distance_m')->take(50)->values();

        return $this->ok($outlet, ['radius_m' => $radius]);
    }

    /** Riwayat join visit koordinator (default hari ini). */
    public function riwayat(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        [$dari, $sampai] = $request->filled('month')
            ? [CarbonImmutable::parse($request->month.'-01')->toDateString(), CarbonImmutable::parse($request->month.'-01')->endOfMonth()->toDateString()]
            : array_fill(0, 2, $request->input('date', $this->hariIni()));

        $baris = $this->dasarJoinVisit()->where('j.koordinator_id', $this->karyawan($request)->id)
            ->whereBetween('j.tanggal', [$dari, $sampai])->orderByDesc('j.mulai_at')->get();

        return $this->ok($baris->map(fn ($j) => $this->bentuk($j))->values(), ['from' => $dari, 'to' => $sampai]);
    }

    public function mulai(Request $request): JsonResponse
    {
        $request->validate($this->aturanUmum() + $this->aturanLokasi() + [
            'sales_id' => ['required', 'integer'],
            'outlet_id' => ['required', 'integer', Rule::exists('mk_outlet', 'id')],
        ]);

        $koordinator = $this->karyawan($request);
        $hari = $this->hari($request);
        $hari->wajibAbsen();

        if ($hari->absensi()->pulang_at) {
            throw ApiError::konflik('already_checked_out', 'Anda sudah absen pulang hari ini.');
        }

        if ($buka = $this->terbuka((int) $koordinator->id)) {
            throw ApiError::konflik('join_visit_open', 'Masih ada join visit yang belum selesai.', ['join_visit_id' => (int) $buka->id]);
        }

        $sales = $this->queryTim($request)->where('id', $request->sales_id)->first()
            ?? throw new ApiError('not_your_team', 'Sales ini bukan anggota tim Anda.', 422);
        $outlet = DB::table('mk_outlet')->where('id', $request->outlet_id)->first();
        $lat = (float) $request->lat;
        $lng = (float) $request->lng;
        $jarak = null;

        // Outlet tanpa koordinat tidak diperiksa (sama seperti kunjungan sales).
        if (Lokasi::sah($outlet->latitude, $outlet->longitude)) {
            $jarak = Lokasi::jarak($lat, $lng, (float) $outlet->latitude, (float) $outlet->longitude);
            $batas = (int) config('mobile.radius_outlet_m');

            if ($jarak > $batas) {
                throw new ApiError('outside_outlet_radius', "Anda $jarak m dari outlet, batas $batas m.", 422, ['distance_m' => $jarak, 'radius_m' => $batas]);
            }
        }

        $id = DB::table('trx_join_visit')->insertGetId([
            'koordinator_id' => $koordinator->id,
            'sales_id' => $sales->id,
            'depo_id' => $sales->depo_id ?? $koordinator->depo_id,
            'outlet_id' => $outlet->id,
            'area_id' => $outlet->area_id,
            'tanggal' => $hari->tanggal,
            'mulai_at' => now(),
            'mulai_lat' => $lat,
            'mulai_lng' => $lng,
            'jarak_m' => $jarak,
            'mock_location' => $request->boolean('is_mock_location'),
        ] + $this->kolomUmum($request));

        return $this->dibuat([
            'join_visit_id' => $id,
            'started_at' => $this->sekarang()->toIso8601String(),
            'distance_to_outlet_m' => $jarak,
        ]);
    }

    public function selesai(Request $request, int $id): JsonResponse
    {
        $request->validate($this->aturanUmum() + $this->aturanLokasi(false) + [
            'note' => ['required', 'string', 'max:2000'],
            'photo_id' => array_merge(['required'], $this->aturanUpload($request)),
        ], ['note.required' => 'Catatan wajib diisi.', 'photo_id.required' => 'Foto wajib.']);

        $jv = DB::table('trx_join_visit')->where('id', $id)->where('koordinator_id', $this->karyawan($request)->id)->first()
            ?? throw ApiError::tidakAda('Join visit tidak ditemukan.');

        if ($jv->selesai_at) {
            throw ApiError::konflik('already_finished', 'Join visit ini sudah selesai.');
        }

        $mulai = CarbonImmutable::parse($jv->mulai_at, 'UTC');
        $durasi = (int) max(0, $mulai->diffInSeconds(now(), true));

        DB::table('trx_join_visit')->where('id', $id)->update([
            'selesai_at' => now(),
            'selesai_lat' => $request->lat,
            'selesai_lng' => $request->lng,
            'durasi_detik' => $durasi,
            'catatan' => trim($request->note),
            'foto_id' => $request->photo_id,
            'selesai_client_ref' => $request->client_ref,
        ]);

        return $this->ok(['join_visit_id' => $id, 'finished_at' => $this->sekarang()->toIso8601String(), 'duration_s' => $durasi]);
    }

    // ------------------------------------------------------------------

    /** Query sales aktif bawahan koordinator yang login. */
    private function queryTim(Request $request)
    {
        return DB::table('mk_karyawan')->where('koordinator_id', $this->karyawan($request)->id)
            ->where('kategori', 'sales')->where('aktif', true);
    }

    public static function terbuka(int $koordinatorId): ?object
    {
        return DB::table('trx_join_visit')->where('koordinator_id', $koordinatorId)->where('sumber', 'motoris')
            ->whereNotNull('mulai_at')->whereNull('selesai_at')->orderByDesc('id')->first();
    }

    /** @return array<int, array{lat: float, lng: float, at: string}> titik GPS terakhir hari ini per sales */
    private function posisiTerakhir(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        $awal = $this->sekarang()->startOfDay()->utc();
        $terakhir = DB::table('trx_tracking')->whereIn('karyawan_id', $ids)->where('recorded_at', '>=', $awal)
            ->groupBy('karyawan_id')->selectRaw('karyawan_id, MAX(recorded_at) AS waktu');

        return DB::table('trx_tracking as t')->joinSub($terakhir, 'm', fn ($j) => $j->on('m.karyawan_id', '=', 't.karyawan_id')->on('m.waktu', '=', 't.recorded_at'))
            ->get(['t.karyawan_id', 't.lat', 't.lng', 't.recorded_at'])
            ->mapWithKeys(fn ($t) => [(int) $t->karyawan_id => [
                'lat' => $this->koordinat($t->lat),
                'lng' => $this->koordinat($t->lng),
                'at' => $this->waktu($t->recorded_at),
            ]])->all();
    }

    private function dasarJoinVisit()
    {
        return DB::table('trx_join_visit as j')
            ->leftJoin('mk_karyawan as s', 's.id', '=', 'j.sales_id')
            ->leftJoin('mk_outlet as o', 'o.id', '=', 'j.outlet_id')
            ->select(['j.*', 's.nama as nama_sales', 'o.nama as nama_outlet', 'o.kode as kode_outlet', 'o.alamat as alamat_outlet']);
    }

    private function bentuk(object $j): array
    {
        return [
            'id' => (int) $j->id,
            'date' => substr((string) $j->tanggal, 0, 10),
            'sales' => ['id' => (int) $j->sales_id, 'name' => $j->nama_sales],
            'outlet' => $j->outlet_id ? ['id' => (int) $j->outlet_id, 'code' => $j->kode_outlet, 'name' => $j->nama_outlet, 'address' => $j->alamat_outlet] : null,
            'started_at' => $this->waktu($j->mulai_at),
            'finished_at' => $this->waktu($j->selesai_at),
            'duration_s' => $j->durasi_detik !== null ? (int) $j->durasi_detik : null,
            'distance_to_outlet_m' => $j->jarak_m !== null ? (int) $j->jarak_m : null,
            'note' => $j->catatan,
            'status' => $j->selesai_at ? 'selesai' : 'berlangsung',
        ];
    }
}
