<?php

namespace App\Http\Controllers;

use App\Support\Akses\Akses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DesignAreaController extends Controller
{
    private const LEVELS = ['province', 'city', 'district', 'village'];

    public function index(Request $request)
    {
        return Inertia::render('Area/Design', [
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

        $prefix = 'AREA-'.now()->format('Ymd').'-';
        $last = DB::table('area_polygon')->where('code', 'like', $prefix.'%')->max('code');
        $code = $prefix.str_pad((int) substr((string) $last, -4) + 1, 4, '0', STR_PAD_LEFT);

        $id = DB::table('area_polygon')->insertGetId([
            ...$data,
            'code' => $code,
            'polygon' => json_encode($data['polygon']),
            'warehouse' => DB::table('area_depo')->where('warehouse_id', $data['warehouse_id'] ?? null)->value('name'),
            'outlet_count' => 0,
        ]);

        return response()->json(['id' => $id, 'code' => $code], 201);
    }

    public function destroy(int $id)
    {
        DB::table('area_polygon')->where('id', $id)->delete();

        return response()->noContent();
    }
}
