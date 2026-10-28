<?php

namespace App\Support\Outlet;

use App\Support\Wilayah\Geometri;
use Illuminate\Support\Facades\DB;

/**
 * Wilayah (provinsi, kota/kab, kecamatan, kelurahan) untuk titik outlet.
 *
 * 1. Kelurahan resmi yang memuat titik, dari batas kelurahan di `batas_wilayah` (diisi per kota oleh
 *    `wilayah:impor-batas` / Generate Polygon Kelurahan), lalu nama induknya dari master `kelurahan` berkode sama.
 * 2. Bila batas kota itu belum dimuat atau titik di luar semua batas: wilayah area outlet (`mk_area.data`,
 *    salinan kolom area_polygon), tanpa kode.
 */
class WilayahOutlet
{
    /** Jangkauan pencarian titik tengah kelurahan di sekitar outlet (derajat, ±11 km). */
    private const JANGKAUAN = 0.1;

    private const MAKS_CALON = 60;

    /**
     * @return array{provinsi: ?string, kota_kabupaten: ?string, kecamatan: ?string, kelurahan: ?string, kode_kelurahan: ?string}
     */
    public function cari(?float $lat, ?float $lng, ?int $areaId = null): array
    {
        $kosong = ['provinsi' => null, 'kota_kabupaten' => null, 'kecamatan' => null, 'kelurahan' => null, 'kode_kelurahan' => null];

        if ($lat !== null && $lng !== null && ($batas = $this->kelurahanDiTitik($lat, $lng))) {
            $master = DB::table('kelurahan')->where('kode', $batas->kode)->first(['provinsi', 'kota_kabupaten', 'kecamatan', 'nama_kelurahan']);
            $area = $master ? [] : $this->dariArea($areaId);

            return [
                'provinsi' => $master->provinsi ?? $area['provinsi'] ?? null,
                'kota_kabupaten' => $master->kota_kabupaten ?? $area['kota_kabupaten'] ?? null,
                'kecamatan' => $master->kecamatan ?? $area['kecamatan'] ?? null,
                'kelurahan' => $master->nama_kelurahan ?? $batas->nama,
                'kode_kelurahan' => $batas->kode,
            ];
        }

        return array_merge($kosong, $this->dariArea($areaId));
    }

    private function kelurahanDiTitik(float $lat, float $lng): ?object
    {
        $calon = DB::table('batas_wilayah')->where('tingkat', 'kelurahan')
            ->whereBetween('lat', [$lat - self::JANGKAUAN, $lat + self::JANGKAUAN])
            ->whereBetween('lng', [$lng - self::JANGKAUAN, $lng + self::JANGKAUAN])
            ->get(['kode', 'nama', 'lat', 'lng'])
            ->sortBy(fn ($b) => ($b->lat - $lat) ** 2 + ($b->lng - $lng) ** 2)
            ->take(self::MAKS_CALON);

        foreach ($calon as $b) {
            $geo = json_decode((string) DB::table('batas_wilayah')->where('kode', $b->kode)->value('geojson'), true);
            $multi = ($geo['type'] ?? null) === 'Polygon' ? [$geo['coordinates']] : ($geo['coordinates'] ?? []);

            if ($multi && Geometri::dalam($lng, $lat, $multi)) {
                return $b;
            }
        }

        return null;
    }

    /** @return array<string, ?string> wilayah area outlet (bisa kosong) */
    private function dariArea(?int $areaId): array
    {
        if (! $areaId) {
            return [];
        }

        $data = json_decode((string) DB::table('mk_area')->where('id', $areaId)->value('data'), true) ?: [];
        $nilai = fn (string $k) => isset($data[$k]) && trim((string) $data[$k]) !== '' && $data[$k] !== '-' ? trim((string) $data[$k]) : null;

        return array_filter([
            'provinsi' => $nilai('province'),
            'kota_kabupaten' => $nilai('city'),
            'kecamatan' => $nilai('district'),
            'kelurahan' => $nilai('village'),
        ], fn ($v) => $v !== null);
    }
}
