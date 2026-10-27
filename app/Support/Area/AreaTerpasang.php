<?php

namespace App\Support\Area;

use App\Support\Wilayah\Geometri;
use Illuminate\Support\Facades\DB;

// Area yang sudah ada di area_polygon dalam satu kotak batas, untuk menandai kotak grid yang tumpang tindih.
// Dicek lewat satu titik di dalam tiap kotak grid (bukan irisan penuh), cukup untuk mencegah area dobel.
// Area lama tarikan Marketama (source_id terisi, segi enam "Generate Polygon V2") tidak dihitung: area baru
// boleh dibuat di sampingnya.
class AreaTerpasang
{
    /** @var list<array{id: int, code: string, name: string, warehouse: ?string, kotak: array, multi: array}> */
    private array $area = [];

    /** @param array $kotak [minLng, minLat, maxLng, maxLat] wilayah yang dicek */
    public function __construct(array $kotak)
    {
        DB::table('area_polygon')->whereNull('source_id')->select(['id', 'code', 'name', 'warehouse', 'polygon', 'geometri'])->orderBy('id')
            ->chunk(1000, function ($baris) use ($kotak) {
                foreach ($baris as $a) {
                    $multi = $a->geometri ? (json_decode($a->geometri, true)['coordinates'] ?? []) : self::dariCincin(json_decode($a->polygon, true) ?: []);
                    $b = Geometri::kotak($multi);
                    if (! $b || $b[2] < $kotak[0] || $b[0] > $kotak[2] || $b[3] < $kotak[1] || $b[1] > $kotak[3]) {
                        continue;
                    }
                    $this->area[] = ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'warehouse' => $a->warehouse, 'kotak' => $b, 'multi' => $multi];
                }
            });
    }

    /** @return array{id: int, code: string, name: string, warehouse: ?string}|null area pertama yang memuat titik */
    public function di(?array $titik): ?array
    {
        if (! $titik) {
            return null;
        }
        [$x, $y] = $titik;
        foreach ($this->area as $a) {
            $b = $a['kotak'];
            if ($x >= $b[0] && $x <= $b[2] && $y >= $b[1] && $y <= $b[3] && Geometri::dalam($x, $y, $a['multi'])) {
                return ['id' => $a['id'], 'code' => $a['code'], 'name' => $a['name'], 'warehouse' => $a['warehouse']];
            }
        }

        return null;
    }

    /** Kolom polygon lama: satu cincin [lng, lat] tanpa titik penutup. */
    private static function dariCincin(array $titik): array
    {
        if (count($titik) < 3) {
            return [];
        }
        if ($titik[0] !== end($titik)) {
            $titik[] = $titik[0];
        }

        return [[$titik]];
    }
}
