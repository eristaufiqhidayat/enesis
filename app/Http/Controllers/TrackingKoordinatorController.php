<?php

namespace App\Http\Controllers;

use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Riwayat lokasi koordinator: data lama Marketama (tracking_koordinator_lokasi) digabung dengan
// titik GPS dari aplikasi HP koordinator (trx_tracking, karyawan_id = koordinator_id).
class TrackingKoordinatorController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = $request->query('tanggal') ?? $this->tanggalTerakhir();

        return view('tracking.history', [
            'page' => config('marketama.pages.tracking_koordinator_history_location'),
            'label' => 'Koordinator',
            'idParam' => 'koordinator_id',
            'people' => $this->daftarKoordinator(),
            'groups' => [],
            'locationsUrl' => route('tracking-koordinator.locations'),
            'countsUrl' => route('tracking-koordinator.counts'),
            'tanggal' => $tanggal,
            'counts' => $this->countsFor($tanggal),
        ]);
    }

    /**
     * Jumlah titik lokasi per koordinator pada satu tanggal, untuk menandai koordinator yang punya data.
     */
    public function counts(Request $request)
    {
        $data = $request->validate(['tanggal' => ['required', 'date']]);

        return response()->json($this->countsFor($data['tanggal']));
    }

    public function locations(Request $request)
    {
        $data = $request->validate([
            'koordinator_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date'],
        ]);

        $id = (int) $data['koordinator_id'];
        $koordinator = DB::table('tracking_koordinator')->where('koordinator_id', $id)->first()
            ?? $this->daftarKoordinator()->firstWhere('id', $id);

        $depo = $koordinator?->depo
            ? DB::table('master_depo')->where('nama', $koordinator->depo)->first(['nama', 'latitude', 'longitude'])
            : null;
        $depo ??= $this->depoMaster($id);

        $lama = DB::table('tracking_koordinator_lokasi')
            ->where('koordinator_id', $id)
            ->whereDate('tanggal', $data['tanggal'])
            ->get(['lat', 'lng', 'waktu', 'akurasi']);

        $locations = $lama->concat($this->titikHp($id, $data['tanggal']))
            ->sortBy('waktu')->values();

        return response()->json([
            'koordinator' => $koordinator,
            'depo' => $depo,
            'total' => $locations->count(),
            'locations' => $locations,
        ]);
    }

    // ------------------------------------------------------------------

    /** Koordinator dari data lama + master karyawan (yang memakai aplikasi HP), tanpa ganda. */
    private function daftarKoordinator(): Collection
    {
        $lama = DB::table('tracking_koordinator')->get(['koordinator_id as id', 'nama', 'telepon', 'depo']);
        $ada = $lama->pluck('id')->flip();

        $master = DB::table('mk_karyawan as k')->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->where('k.kategori', 'koordinator')->where('k.aktif', true)
            ->get(['k.id', 'k.nama', 'k.no_hp as telepon', 'd.nama as depo'])
            ->reject(fn ($k) => isset($ada[$k->id]));

        return $lama->concat($master)->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    private function countsFor(string $tanggal): array
    {
        $hasil = DB::table('tracking_koordinator_lokasi')
            ->whereDate('tanggal', $tanggal)
            ->groupBy('koordinator_id')
            ->selectRaw('koordinator_id, count(*) as total')
            ->pluck('total', 'koordinator_id')
            ->map(fn ($total) => (int) $total)
            ->all();

        [$dari, $sampai] = $this->rentangUtc($tanggal);
        $hp = DB::table('trx_tracking as t')->join('mk_karyawan as k', 'k.id', '=', 't.karyawan_id')
            ->where('k.kategori', 'koordinator')->whereBetween('t.recorded_at', [$dari, $sampai])
            ->groupBy('t.karyawan_id')->selectRaw('t.karyawan_id, count(*) as total')
            ->pluck('total', 'karyawan_id');

        foreach ($hp as $id => $total) {
            $hasil[$id] = ($hasil[$id] ?? 0) + (int) $total;
        }

        return $hasil;
    }

    /** Titik GPS dari HP pada satu tanggal (WIB), bentuknya sama dengan data lama. */
    private function titikHp(int $karyawanId, string $tanggal): Collection
    {
        [$dari, $sampai] = $this->rentangUtc($tanggal);
        $zona = Pengaturan::zona();

        return DB::table('trx_tracking')->where('karyawan_id', $karyawanId)->whereBetween('recorded_at', [$dari, $sampai])
            ->get(['lat', 'lng', 'recorded_at', 'akurasi_m'])
            ->map(fn ($t) => (object) [
                'lat' => $t->lat,
                'lng' => $t->lng,
                'waktu' => CarbonImmutable::parse($t->recorded_at, 'UTC')->setTimezone($zona)->format('Y-m-d H:i:s'),
                'akurasi' => $t->akurasi_m,
            ]);
    }

    /** @return array{0: string, 1: string} awal & akhir tanggal WIB dalam UTC (kolom recorded_at) */
    private function rentangUtc(string $tanggal): array
    {
        $awal = CarbonImmutable::parse($tanggal, Pengaturan::zona())->startOfDay();

        return [$awal->utc()->format('Y-m-d H:i:s'), $awal->endOfDay()->utc()->format('Y-m-d H:i:s')];
    }

    /** Tanggal terbaru yang punya data (lama atau HP); hari ini bila belum ada. */
    private function tanggalTerakhir(): string
    {
        $lama = DB::table('tracking_koordinator_lokasi')->max('tanggal');
        $hp = DB::table('trx_tracking as t')->join('mk_karyawan as k', 'k.id', '=', 't.karyawan_id')
            ->where('k.kategori', 'koordinator')->max('t.recorded_at');
        $hp = $hp ? CarbonImmutable::parse($hp, 'UTC')->setTimezone(Pengaturan::zona())->toDateString() : null;

        return collect([$lama ? substr((string) $lama, 0, 10) : null, $hp])->filter()->max()
            ?? CarbonImmutable::now(Pengaturan::zona())->toDateString();
    }

    /** Lokasi depo dari master (mk_depo) untuk koordinator yang tidak ada di data lama. */
    private function depoMaster(int $karyawanId): ?object
    {
        $depoId = DB::table('mk_karyawan')->where('id', $karyawanId)->value('depo_id');
        $depo = $depoId ? Pengaturan::depo((int) $depoId) : null;

        return $depo && $depo['lat'] !== null ? (object) ['nama' => $depo['nama'], 'latitude' => $depo['lat'], 'longitude' => $depo['lng']] : null;
    }
}
