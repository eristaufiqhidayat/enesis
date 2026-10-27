<?php

namespace App\Support\Menu;

use App\Models\User;
use App\Support\Area\AreaMk;
use App\Support\Database\Sql;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Menu Master Area dibaca langsung dari area_polygon (tabel yang sama dengan Design Area, Generate Polygon
 * Kelurahan, dan Auto Grid), bukan dari salinan datar master_area. Area baru buatan Enesis ikut tampil.
 * Total toko dan nama sales dihitung dari mk_outlet / mk_karyawan_area lewat ID mk_area (AreaMk).
 */
class SumberMasterArea implements SumberTabel
{
    public function tabelUtama(): string
    {
        return 'area_polygon';
    }

    public function query(?User $user): Builder
    {
        $idMk = AreaMk::idSql('ap');
        $gabung = Sql::gabungUnik('k.nama');

        return DB::table('area_polygon as ap')
            ->leftJoin('area_depo as d', 'd.warehouse_id', '=', 'ap.warehouse_id')
            ->select([
                'ap.id',
                'ap.code as kode',
                'ap.name as nama',
                DB::raw("(SELECT COUNT(*) FROM mk_outlet o WHERE o.area_id = {$idMk}) AS total_toko"),
                DB::raw("COALESCE((SELECT {$gabung} FROM mk_karyawan_area ka JOIN mk_karyawan k ON k.id = ka.karyawan_id WHERE ka.area_id = {$idMk}), ap.salesperson) AS nama_sales"),
                'ap.warehouse as depo',
                'ap.branch_area as wilayah_depo',
                'ap.province as provinsi',
                'ap.city as kota_kabupaten',
                'ap.district as kecamatan',
                'ap.village as kelurahan',
                'ap.diubah_oleh as diupdate_oleh',
                'ap.diubah_pada as diupdate_pada',
                // Bahan jarak ke depo (dihitung per baris di ubahBaris, lalu dibuang).
                'ap.polygon as _polygon', 'd.lat as _lat', 'd.lng as _lng',
            ])
            ->orderByDesc('ap.id');
    }

    public function ubahBaris(object $baris): object
    {
        [$dekat, $jauh] = self::jarak(json_decode((string) $baris->_polygon, true) ?: [], $baris->_lat, $baris->_lng);
        $baris->jarak_terdekat_ke_depo = $dekat;
        $baris->jarak_terjauh_ke_depo = $jauh;
        $baris->diupdate_pada = JamLokal::format($baris->diupdate_pada);
        unset($baris->_polygon, $baris->_lat, $baris->_lng);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['ap.code', 'ap.name', 'ap.warehouse', 'ap.branch_area', 'ap.province', 'ap.city', 'ap.district', 'ap.village', 'ap.salesperson'];
    }

    /**
     * Jarak titik sudut area terdekat dan terjauh dari depo, mis. ["18,67 km", "20,00 km"] seperti Marketama.
     *
     * @param  list<array{0: float, 1: float}>  $cincin  [lng, lat]
     * @return array{0: ?string, 1: ?string}
     */
    public static function jarak(array $cincin, $lat, $lng): array
    {
        if ($lat === null || $lng === null || ! $cincin) {
            return [null, null];
        }
        $km = array_map(function ($p) use ($lat, $lng) {
            $rad = fn ($d) => deg2rad((float) $d);
            $a = sin(($rad($p[1]) - $rad($lat)) / 2) ** 2 + cos($rad($lat)) * cos($rad($p[1])) * sin(($rad($p[0]) - $rad($lng)) / 2) ** 2;

            return 2 * 6371.0088 * asin(min(1, sqrt($a)));
        }, array_filter($cincin, fn ($p) => is_array($p) && count($p) >= 2));
        if (! $km) {
            return [null, null];
        }
        $teks = fn ($n) => number_format($n, 2, ',', '.').' km';

        return [$teks(min($km)), $teks(max($km))];
    }
}
