<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use App\Support\Area\KodeArea;
use App\Support\Wilayah\Geometri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DesignAreaController extends Controller
{
    private const LEVELS = ['province', 'city', 'district', 'village'];

    // Batas titik satu area yang boleh disimpan dari editor (polygon resmi BIG bisa ribuan titik).
    private const MAKS_TITIK = 50000;

    public function index(Request $request)
    {
        // ?area=<id>: buka langsung area itu (dari tombol Ubah di Generate Polygon Kelurahan).
        $fokus = $request->integer('area')
            ? DB::table('area_polygon')->where('id', $request->integer('area'))->first(['id', 'province', 'city', 'district', 'village'])
            : null;

        return Inertia::render('Area/Design', [
            'fokus' => $fokus,
            'slug' => 'master_area',
            'title' => 'Design Polygon Area',
            'provinces' => DB::table('area_polygon')->whereNotNull('province')->distinct()->orderBy('province')->pluck('province'),
            'warehouses' => DB::table('area_depo')->orderBy('name')->get(['warehouse_id', 'name']),
            'bisaUbah' => Akses::bisa($request->user(), 'master_area', 'ubah'),
            'bisaHapus' => Akses::bisa($request->user(), 'master_area', 'hapus'),
            'kembaliUrl' => route('menu', 'master_area'),
            'optionsUrl' => route('design-area.options'),
            'areasUrl' => route('design-area.areas'),
            'storeUrl' => route('design-area.store'),
            'destroyUrl' => url('master_area/design_area/areas'),
            'updateUrl' => url('master_area/design_area/areas'),
            'generateUrl' => route('kelurahan-polygon.index'),
            'autoGridUrl' => route('auto-grid.index'),
        ]);
    }

    /** Pilihan dropdown bertingkat: kota per provinsi, kecamatan per kota, kelurahan per kecamatan. */
    public function options(Request $request)
    {
        $level = $request->validate(['level' => ['required', 'in:city,district,village']])['level'];
        $query = DB::table('area_polygon')->whereNotNull($level);

        foreach (self::LEVELS as $parent) {
            if ($parent === $level) {
                break;
            }
            if ($request->filled($parent)) {
                $query->where($parent, $request->query($parent));
            }
        }

        return $query->distinct()->orderBy($level)->pluck($level);
    }

    public function areas(Request $request)
    {
        $request->validate(['province' => ['required', 'string']]);
        $query = DB::table('area_polygon');

        foreach (self::LEVELS as $level) {
            if ($request->filled($level)) {
                $query->where($level, $request->query($level));
            }
        }

        $areas = $query->orderBy('name')->get()->map(function ($area) {
            $area->polygon = json_decode($area->polygon);
            $area->geometri = $area->geometri ? json_decode($area->geometri) : null;

            return $area;
        });

        return response()->json([
            'areas' => $areas,
            'warehouses' => DB::table('area_depo')->where('province', $request->query('province'))->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'warehouse_id' => ['nullable', 'integer'],
            'info' => ['nullable', 'string'],
            'province' => ['required', 'string'],
            'city' => ['nullable', 'string'],
            'district' => ['nullable', 'string'],
            'village' => ['nullable', 'string'],
            'polygon' => ['required', 'array', 'min:3'],
            'polygon.*' => ['array', 'size:2'],
            'polygon.*.*' => ['numeric'],
        ]);

        [$id, $code] = DB::transaction(function () use ($data) {
            $code = KodeArea::berikutnya()[0];

            return [DB::table('area_polygon')->insertGetId([
                ...$data,
                'code' => $code,
                'polygon' => json_encode($data['polygon']),
                'warehouse' => DB::table('area_depo')->where('warehouse_id', $data['warehouse_id'] ?? null)->value('name'),
                'outlet_count' => 0,
                'sumber_polygon' => 'gambar',
            ]), $code];
        });

        return response()->json(['id' => $id, 'code' => $code], 201);
    }

    /**
     * Ubah data area (nama, depo, keterangan) dan, bila dikirim, bentuknya. Bentuk dikirim sebagai koordinat
     * GeoJSON MultiPolygon [lng, lat] sehingga area hasil Generate Polygon Kelurahan (banyak pulau/lubang) tetap utuh.
     */
    public function update(Request $request, int $id)
    {
        $area = DB::table('area_polygon')->where('id', $id)->first();
        abort_unless($area, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'warehouse_id' => ['nullable', 'integer', 'exists:area_depo,warehouse_id'],
            'info' => ['nullable', 'string'],
            'geometri' => ['nullable', 'array', 'min:1'],
        ], [], ['name' => 'nama area', 'warehouse_id' => 'depo', 'geometri' => 'bentuk area']);

        $ubah = [
            'name' => $data['name'],
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'warehouse' => DB::table('area_depo')->where('warehouse_id', $data['warehouse_id'] ?? null)->value('name'),
            'info' => $data['info'] ?? null,
        ];

        if (! empty($data['geometri'])) {
            $multi = self::multiValid($data['geometri']);
            if ($multi === null) {
                return response()->json(['message' => 'Bentuk area tidak valid: tiap cincin minimal 3 titik dengan koordinat [lng, lat], total maksimal '.number_format(self::MAKS_TITIK, 0, ',', '.').' titik.'], 422);
            }
            $ubah['geometri'] = json_encode(['type' => 'MultiPolygon', 'coordinates' => $multi]);
            $ubah['polygon'] = json_encode(Geometri::cincinTerbesar($multi));
            $ubah['sumber_polygon'] = 'diubah';
        }

        DB::table('area_polygon')->where('id', $id)->update($ubah);

        $area = DB::table('area_polygon')->where('id', $id)->first();
        $area->polygon = json_decode($area->polygon);
        $area->geometri = $area->geometri ? json_decode($area->geometri) : null;

        return response()->json($area);
    }

    public function destroy(int $id)
    {
        abort_unless(DB::table('area_polygon')->where('id', $id)->delete(), 404);

        return response()->noContent();
    }

    /** Koordinat MultiPolygon dari editor → bersih (angka, 6 desimal, cincin tertutup) atau null bila tidak valid. */
    private static function multiValid(array $multi): ?array
    {
        $hasil = [];
        $total = 0;
        foreach ($multi as $polygon) {
            if (! is_array($polygon) || ! $polygon) {
                return null;
            }
            $cincin = [];
            foreach ($polygon as $ring) {
                if (! is_array($ring)) {
                    return null;
                }
                $titik = [];
                foreach ($ring as $p) {
                    if (! is_array($p) || count($p) !== 2 || ! is_numeric($p[0] ?? null) || ! is_numeric($p[1] ?? null)
                        || abs($p[0]) > 180 || abs($p[1]) > 90) {
                        return null;
                    }
                    $titik[] = [round((float) $p[0], 6), round((float) $p[1], 6)];
                }
                if ($titik && $titik[0] !== end($titik)) {
                    $titik[] = $titik[0];
                }
                if (count($titik) < 4) {
                    return null;
                }
                $total += count($titik) - 1;
                $cincin[] = $titik;
            }
            $hasil[] = $cincin;
        }

        return $total <= self::MAKS_TITIK ? $hasil : null;
    }
}
