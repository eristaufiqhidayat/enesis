<?php

namespace App\Support\Area;

use App\Support\Wilayah\BatasWilayah;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Bahan bersama Generate Polygon Kelurahan dan Auto Grid: kelurahan satu kota/kecamatan dari master Enesis,
// batas resminya, dan ID wilayah untuk kolom *_id area_polygon (tabel wilayah masih berelasi lewat nama).
class WilayahKota
{
    public function __construct(private BatasWilayah $batas) {}

    /** Pastikan batas kelurahan kota sudah tersimpan; mengembalikan pesan peringatan bila gagal. */
    public function siapkanBatas(object $kota): ?string
    {
        if (! $kota->kode) {
            return 'Kota/kabupaten ini tidak punya kode Kemendagri, jadi batas resminya tidak bisa dicari.';
        }

        try {
            $this->batas->pastikanKota((string) $kota->kode);
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function kelurahan(object $kota, ?object $kecamatan = null): Collection
    {
        return DB::table('kelurahan')
            ->where('provinsi', $kota->provinsi)
            ->where('kota_kabupaten', $kota->nama_kota_kabupaten)
            ->when($kecamatan, fn ($q) => $q->where('kecamatan', $kecamatan->nama_kecamatan))
            ->orderBy('kecamatan')->orderBy('nama_kelurahan')
            ->get(['id', 'kode', 'provinsi', 'kota_kabupaten', 'kecamatan', 'nama_kelurahan']);
    }

    /** Batas resmi (baris batas_wilayah) per kode kelurahan. */
    public function batasPerKode(Collection $kelurahan): Collection
    {
        return DB::table('batas_wilayah')->where('tingkat', 'kelurahan')
            ->whereIn('kode', $kelurahan->pluck('kode')->filter()->all())
            ->get(['kode', 'lat', 'lng', 'geojson', 'jumlah_titik', 'luas_km2', 'sumber'])->keyBy('kode');
    }

    /** @return array{provinsi: array, kota: array, kecamatan: array} kunci nama → id */
    public function idWilayah(Collection $kelurahan): array
    {
        $provinsi = $kelurahan->pluck('provinsi')->unique()->all();

        return [
            'provinsi' => DB::table('provinsi')->whereIn('nama_provinsi', $provinsi)->pluck('id', 'nama_provinsi')->all(),
            'kota' => DB::table('kota_kabupaten')->whereIn('provinsi', $provinsi)->get(['id', 'provinsi', 'nama_kota_kabupaten'])
                ->mapWithKeys(fn ($r) => [$r->provinsi.'|'.$r->nama_kota_kabupaten => $r->id])->all(),
            'kecamatan' => DB::table('kecamatan')->whereIn('provinsi', $provinsi)
                ->whereIn('kota_kabupaten', $kelurahan->pluck('kota_kabupaten')->unique()->all())
                ->get(['id', 'provinsi', 'kota_kabupaten', 'nama_kecamatan'])
                ->mapWithKeys(fn ($r) => [$r->provinsi.'|'.$r->kota_kabupaten.'|'.$r->nama_kecamatan => $r->id])->all(),
        ];
    }

    /** Kolom wilayah area_polygon untuk satu kelurahan (nama + id). */
    public static function kolomWilayah(object $k, array $id): array
    {
        return [
            'province' => $k->provinsi,
            'province_id' => $id['provinsi'][$k->provinsi] ?? null,
            'city' => $k->kota_kabupaten,
            'city_id' => $id['kota'][$k->provinsi.'|'.$k->kota_kabupaten] ?? null,
            'district' => $k->kecamatan,
            'district_id' => $id['kecamatan'][$k->provinsi.'|'.$k->kota_kabupaten.'|'.$k->kecamatan] ?? null,
            'village' => $k->nama_kelurahan,
            'village_id' => $k->id,
            'kode_kelurahan' => $k->kode,
        ];
    }
}
