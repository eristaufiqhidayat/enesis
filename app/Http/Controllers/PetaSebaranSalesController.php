<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use App\Support\Area\AreaMk;
use App\Support\Menu\Motoris\SumberLastSeenSales;
use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

// Peta Sebaran Sales: polygon Master Area (area_polygon) se-Indonesia beserta nama sales yang ditugaskan di
// tiap area (mk_karyawan_area). Peta diperkecil = ringkasan per depo; diperbesar = area di layar diambil lewat
// endpoint area (kotak batas min/max lng/lat). Lapisan tambahan: posisi GPS terakhir sales (Tracking Last Seen).
// Cakupan role berlaku: akun selain "semua" hanya melihat area depo / sales dalam cakupannya.
class PetaSebaranSalesController extends Controller
{
    // Area per permintaan; lebih dari ini peta diminta diperbesar.
    public const MAKS_AREA = 2500;

    public function index(): Response
    {
        return Inertia::render('Tracking/Sebaran', [
            'slug' => 'tracking_sales',
            'title' => 'Peta Sebaran Sales',
            'dataUrl' => route('peta-sebaran-sales.data'),
            'areaUrl' => route('peta-sebaran-sales.area'),
            'masterAreaUrl' => route('menu', 'master_area'),
            'designUrl' => route('design-area.index'),
            'lastSeenUrl' => route('menu', 'tracking_sales'),
        ]);
    }

    /** Semua sales aktif dalam cakupan akun, dengan posisi terakhir dan status kebaruannya. */
    public function data(Request $request, SumberLastSeenSales $sumber): JsonResponse
    {
        $sekarang = Pengaturan::sekarang();
        $hariIni = $sekarang->startOfDay();

        $sales = $sumber->query($request->user())->addSelect(['k.depo_id', 'k.koordinator_id'])->get()
            ->map(fn ($b) => $sumber->ubahBaris($b))
            ->map(function ($b) use ($sekarang, $hariIni) {
                $waktu = $b->sumber ? CarbonImmutable::parse($b->terakhir_terlihat, $sekarang->getTimezone()) : null;
                $status = match (true) {
                    $b->latitude === null || $b->longitude === null || ! $waktu => 'belum',
                    $waktu->gte($hariIni) => 'hari_ini',
                    $waktu->gte($hariIni->subDays(7)) => 'minggu',
                    default => 'lama',
                };

                return [
                    'id' => (int) $b->id,
                    'nama' => $b->nama,
                    'telepon' => $b->telepon,
                    'depoId' => $b->depo_id !== null ? (int) $b->depo_id : null,
                    'depo' => $b->depo,
                    'koordinator' => $b->koordinator,
                    'lat' => $b->latitude,
                    'lng' => $b->longitude,
                    'waktu' => $b->sumber ? $b->terakhir_terlihat : null,
                    'sejak' => $b->sejak,
                    'sumber' => $b->sumber,
                    'baterai' => $b->baterai,
                    'lokasiPalsu' => (bool) $b->lokasi_palsu,
                    'status' => $status,
                ];
            })->values();

        // Jumlah area per sales (untuk daftar) dan ringkasan area per depo (untuk peta diperkecil).
        $areaPerSales = DB::table('mk_karyawan_area')->whereIn('karyawan_id', $sales->pluck('id')->all() ?: [0])
            ->selectRaw('karyawan_id, COUNT(*) as n')->groupBy('karyawan_id')->pluck('n', 'karyawan_id');
        $sales = $sales->map(fn ($s) => $s + ['jumlahArea' => (int) ($areaPerSales[$s['id']] ?? 0)]);

        $depo = $this->ringkasanDepo($this->areaDalamCakupan($request));

        return response()->json([
            'sales' => $sales->values(),
            'depo' => $depo,
            'diperbarui' => $sekarang->format('Y-m-d H:i'),
        ]);
    }

    /**
     * Area di layar (?bbox=minLng,minLat,maxLng,maxLat) atau area satu sales (?karyawan_id=), beserta polygon dan
     * sales yang ditugaskan di tiap area.
     */
    public function area(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bbox' => ['required_without:karyawan_id', 'string', 'regex:/^-?[\d.]+,-?[\d.]+,-?[\d.]+,-?[\d.]+$/'],
            'karyawan_id' => ['nullable', 'integer'],
        ]);

        $q = $this->areaDalamCakupan($request);
        if (! empty($data['karyawan_id'])) {
            $idMk = DB::table('mk_karyawan_area')->where('karyawan_id', $data['karyawan_id'])->pluck('area_id')->map(fn ($id) => (int) $id);
            [$enesis, $lama] = $idMk->partition(fn ($id) => $id >= AreaMk::ID_AWAL);
            $q->where(fn ($w) => $w->whereIn('ap.source_id', $lama->all() ?: [0])
                ->orWhereIn('ap.id', $enesis->map(fn ($id) => $id - AreaMk::ID_AWAL)->all() ?: [0]));
        } else {
            [$x1, $y1, $x2, $y2] = array_map('floatval', explode(',', $data['bbox']));
            $q->where('ap.max_lng', '>=', $x1)->where('ap.min_lng', '<=', $x2)->where('ap.max_lat', '>=', $y1)->where('ap.min_lat', '<=', $y2);
        }

        $jumlah = (clone $q)->count();
        if ($jumlah > self::MAKS_AREA) {
            return response()->json(['terlaluBanyak' => true, 'jumlah' => $jumlah, 'area' => []]);
        }

        $area = $q->orderBy('ap.id')->get(['ap.id', 'ap.source_id', 'ap.code', 'ap.name', 'ap.warehouse', 'ap.village', 'ap.district', 'ap.city', 'ap.polygon', 'ap.geometri']);
        $idMk = $area->map(fn ($a) => AreaMk::id($a))->all();
        $sales = DB::table('mk_karyawan_area as ka')->join('mk_karyawan as k', 'k.id', '=', 'ka.karyawan_id')
            ->whereIn('ka.area_id', $idMk ?: [0])->where('k.aktif', true)
            ->orderByDesc('ka.aktif')->orderBy('k.nama')
            ->get(['ka.area_id', 'ka.aktif', 'k.id', 'k.nama'])->groupBy('area_id');

        return response()->json([
            'terlaluBanyak' => false,
            'jumlah' => $jumlah,
            'area' => $area->map(function ($a) use ($sales) {
                $id = AreaMk::id($a);
                $multi = $a->geometri ? (json_decode($a->geometri, true)['coordinates'] ?? null) : null;
                if (! $multi) {
                    $cincin = json_decode((string) $a->polygon, true) ?: [];
                    if ($cincin && $cincin[0] !== end($cincin)) {
                        $cincin[] = $cincin[0];
                    }
                    $multi = count($cincin) >= 4 ? [[$cincin]] : null;
                }

                return [
                    'id' => (int) $a->id,
                    'kode' => $a->code,
                    'nama' => $a->name,
                    'depo' => $a->warehouse,
                    'wilayah' => implode(', ', array_filter([$a->village, $a->district, $a->city])),
                    'geometri' => $multi,
                    'sales' => ($sales[$id] ?? collect())->map(fn ($s) => ['id' => (int) $s->id, 'nama' => $s->nama, 'aktif' => (bool) $s->aktif])->values(),
                ];
            })->filter(fn ($a) => $a['geometri'])->values(),
        ]);
    }

    /** Query area_polygon (alias ap) yang boleh dilihat akun: semua, atau area depo / sales dalam cakupannya. */
    private function areaDalamCakupan(Request $request)
    {
        $q = DB::table('area_polygon as ap');
        if (Akses::cakupan($request->user()) === 'semua') {
            return $q;
        }

        $karyawan = Akses::batasiKaryawan(DB::table('mk_karyawan as k')->where('k.kategori', 'sales'), $request->user());
        $depo = (clone $karyawan)->whereNotNull('k.depo_id')->distinct()->pluck('k.depo_id')->all();
        $idMk = DB::table('mk_karyawan_area')->whereIn('karyawan_id', (clone $karyawan)->pluck('k.id')->all() ?: [0])->pluck('area_id')->map(fn ($id) => (int) $id);
        [$enesis, $lama] = $idMk->partition(fn ($id) => $id >= AreaMk::ID_AWAL);

        return $q->where(fn ($w) => $w->whereIn('ap.warehouse_id', $depo ?: [0])
            ->orWhereIn('ap.source_id', $lama->all() ?: [0])
            ->orWhereIn('ap.id', $enesis->map(fn ($id) => $id - AreaMk::ID_AWAL)->all() ?: [0]));
    }

    /** Per depo: jumlah area, area tanpa sales, sales yang ditugaskan, dan titik tampil (lokasi depo atau tengah areanya). */
    private function ringkasanDepo($q)
    {
        $idMk = AreaMk::idSql('ap');
        $baris = (clone $q)->whereNotNull('ap.min_lat')
            ->selectRaw("ap.warehouse_id, MAX(ap.warehouse) as nama, COUNT(*) as area,
                SUM(CASE WHEN EXISTS (SELECT 1 FROM mk_karyawan_area ka WHERE ka.area_id = {$idMk}) THEN 0 ELSE 1 END) as tanpa_sales,
                AVG((ap.min_lat + ap.max_lat) / 2) as lat, AVG((ap.min_lng + ap.max_lng) / 2) as lng,
                MIN(ap.min_lng) as x1, MIN(ap.min_lat) as y1, MAX(ap.max_lng) as x2, MAX(ap.max_lat) as y2")
            ->groupBy('ap.warehouse_id')->get();

        $lokasi = DB::table('area_depo')->whereIn('warehouse_id', $baris->pluck('warehouse_id')->filter()->all() ?: [0])->get(['warehouse_id', 'name', 'lat', 'lng'])->keyBy('warehouse_id');
        // Sales aktif yang ditugaskan di area depo itu (bisa berasal dari depo lain).
        $sales = (clone $q)->join('mk_karyawan_area as ka', 'ka.area_id', '=', DB::raw($idMk))
            ->join('mk_karyawan as k', 'k.id', '=', 'ka.karyawan_id')->where('k.aktif', true)
            ->selectRaw('ap.warehouse_id, COUNT(DISTINCT ka.karyawan_id) as n')->groupBy('ap.warehouse_id')->pluck('n', 'warehouse_id');

        return $baris->map(function ($b) use ($lokasi, $sales) {
            $d = $b->warehouse_id !== null ? $lokasi->get($b->warehouse_id) : null;

            return [
                'id' => $b->warehouse_id !== null ? (int) $b->warehouse_id : null,
                'nama' => $d->name ?? $b->nama ?? 'Tanpa depo',
                'lat' => $d && $d->lat !== null ? (float) $d->lat : (float) $b->lat,
                'lng' => $d && $d->lng !== null ? (float) $d->lng : (float) $b->lng,
                'jumlahArea' => (int) $b->area,
                'tanpaSales' => (int) $b->tanpa_sales,
                'jumlahSales' => (int) ($sales[$b->warehouse_id ?? ''] ?? 0),
                'kotak' => [(float) $b->x1, (float) $b->y1, (float) $b->x2, (float) $b->y2],
            ];
        })->values();
    }
}
