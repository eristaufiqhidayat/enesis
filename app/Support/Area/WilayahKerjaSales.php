<?php

namespace App\Support\Area;

use Illuminate\Support\Facades\DB;

// Peta wilayah kerja seorang sales untuk halaman akun (lihat & edit): area-area di mk_karyawan_area beserta
// polygonnya di area_polygon (= baris menu Master Area), titik outlet area rute, dan lokasi depo.
class WilayahKerjaSales
{
    // Titik outlet di peta dibatasi supaya halaman tetap ringan; daftar lengkap ada di tabel outlet.
    public const MAKS_OUTLET = 3000;

    /**
     * @param  list<int>  $rute  ID mk_area rute sales (HariKerja::areaKaryawan)
     * @return array{area: list<array>, outlet: list<array>, outletTerpotong: bool, depo: ?array}
     */
    public static function untuk(int $karyawanId, array $rute): array
    {
        $penugasan = DB::table('mk_karyawan_area')->where('karyawan_id', $karyawanId)->pluck('area_id')->map(fn ($id) => (int) $id)->all();
        $polygon = self::polygon($penugasan);

        $area = [];
        foreach ($penugasan as $idMk) {
            $p = $polygon[$idMk] ?? null;
            $area[] = [
                'areaId' => $idMk,
                'areaPolygonId' => $p?->id,
                'kode' => $p?->code,
                'nama' => $p?->name,
                'depo' => $p?->warehouse,
                'kelurahan' => $p?->village,
                'kecamatan' => $p?->district,
                'kota' => $p?->city,
                'rute' => in_array($idMk, $rute, true),
                // Koordinat MultiPolygon [lng, lat]; null bila area belum punya polygon di area_polygon.
                'geometri' => $p ? self::multi($p) : null,
            ];
        }

        $outlet = DB::table('mk_outlet')->whereIn('area_id', $rute ?: [0])
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderBy('id')->limit(self::MAKS_OUTLET + 1)
            ->get(['kode', 'nama', 'area_id', 'latitude', 'longitude']);

        $depoId = DB::table('mk_karyawan')->where('id', $karyawanId)->value('depo_id');
        $depo = $depoId ? DB::table('area_depo')->where('warehouse_id', $depoId)->first(['name', 'lat', 'lng']) : null;

        return [
            'area' => $area,
            'outlet' => $outlet->take(self::MAKS_OUTLET)->map(fn ($o) => [
                'kode' => $o->kode,
                'nama' => $o->nama,
                'areaId' => (int) $o->area_id,
                'lat' => (float) $o->latitude,
                'lng' => (float) $o->longitude,
            ])->values()->all(),
            'outletTerpotong' => $outlet->count() > self::MAKS_OUTLET,
            'depo' => $depo && $depo->lat !== null && $depo->lng !== null ? ['nama' => $depo->name, 'lat' => (float) $depo->lat, 'lng' => (float) $depo->lng] : null,
        ];
    }

    /** Baris area_polygon per ID mk_area (ID Marketama lewat source_id, ID Enesis lewat offset AreaMk::ID_AWAL). */
    private static function polygon(array $idMk): array
    {
        if (! $idMk) {
            return [];
        }
        [$enesis, $marketama] = collect($idMk)->partition(fn ($id) => $id >= AreaMk::ID_AWAL);

        $baris = DB::table('area_polygon')
            ->where(fn ($q) => $q->whereIn('source_id', $marketama->all() ?: [0])
                ->orWhereIn('id', $enesis->map(fn ($id) => $id - AreaMk::ID_AWAL)->all() ?: [0]))
            ->get(['id', 'source_id', 'code', 'name', 'warehouse', 'village', 'district', 'city', 'polygon', 'geometri']);

        return $baris->keyBy(fn ($r) => AreaMk::id($r))->all();
    }

    private static function multi(object $p): ?array
    {
        if ($p->geometri) {
            return json_decode($p->geometri, true)['coordinates'] ?? null;
        }
        $cincin = json_decode((string) $p->polygon, true) ?: [];
        if (count($cincin) < 3) {
            return null;
        }
        if ($cincin[0] !== end($cincin)) {
            $cincin[] = $cincin[0];
        }

        return [[$cincin]];
    }
}
