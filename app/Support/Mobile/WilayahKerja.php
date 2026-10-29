<?php

namespace App\Support\Mobile;

use App\Support\Area\WilayahKerjaSales;
use App\Support\Wilayah\Geometri;
use Illuminate\Support\Facades\DB;

// Area kerja untuk absen (Master Area, bukan radius depo — keputusan 28 Okt 2026, lihat
// claude/menu-absensi-master-area.md): area aktif sales sendiri, atau gabungan area aktif
// seluruh sales di tim untuk koordinator. Dipakai oleh ProfilController::config() (field
// work_areas untuk aplikasi HP) dan AbsensiController::checkIn() (validasi server-side —
// tidak cukup mengandalkan pengecekan di HP). Mobile dan server memakai geometri yang sama
// persis (cincin terluar saja, tanpa lubang; lihat ringkas()) supaya hasilnya tidak pernah beda.
class WilayahKerja
{
    /** Area aktif sales (atau semua bila tidak ada yang ditandai aktif) — lihat HariKerja::areaKaryawan(). */
    public static function untukSales(int $karyawanId): array
    {
        return self::ringkas(HariKerja::areaKaryawan($karyawanId));
    }

    /** Gabungan area aktif seluruh sales bawahan koordinator (distinct). */
    public static function untukKoordinator(int $koordinatorId): array
    {
        $idArea = DB::table('mk_karyawan')->where('koordinator_id', $koordinatorId)
            ->where('kategori', 'sales')->where('aktif', true)->pluck('id')
            ->flatMap(fn ($id) => HariKerja::areaKaryawan((int) $id))
            ->unique()->values()->all();

        return self::ringkas($idArea);
    }

    /**
     * Area kerja untuk peran akun yang login: sales → areanya sendiri, koordinator → gabungan tim.
     *
     * @return list<array{id:int,name:?string,polygon:list<array{lat:float,lng:float}>}>
     */
    public static function untukAkun(string $peran, int $karyawanId): array
    {
        return $peran === 'koordinator' ? self::untukKoordinator($karyawanId) : self::untukSales($karyawanId);
    }

    /** true bila titik ada di dalam salah satu area pada daftar [area kerja] (hasil ringkas()/untukAkun()). */
    public static function titikDalam(float $lat, float $lng, array $areaKerja): bool
    {
        foreach ($areaKerja as $a) {
            if (self::titikDalamPoligon($lat, $lng, $a['polygon'])) {
                return true;
            }
        }

        return false;
    }

    /** Ray casting pada satu cincin [{lat, lng}, ...] — sama persis dengan lib/core/geo.dart di aplikasi HP. */
    private static function titikDalamPoligon(float $lat, float $lng, array $polygon): bool
    {
        $n = count($polygon);
        if ($n < 3) {
            return false;
        }

        $dalam = false;
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $latI = $polygon[$i]['lat'];
            $lngI = $polygon[$i]['lng'];
            $latJ = $polygon[$j]['lat'];
            $lngJ = $polygon[$j]['lng'];

            $potong = (($lngI > $lng) !== ($lngJ > $lng)) && ($lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI);
            if ($potong) {
                $dalam = ! $dalam;
            }
        }

        return $dalam;
    }

    /**
     * ID mk_area → {id, name, polygon [{lat,lng}]}; area tanpa polygon (atau polygon < 3 titik)
     * dilewati — tidak ikut membentuk area kerja. Dipakai sama oleh /config dan validasi absen,
     * supaya titik yang dianggap "dalam area" di HP selalu konsisten dengan di server.
     *
     * Hanya cincin terluar polygon terluas per area yang dipakai (lubang dan bagian terpisah
     * diabaikan) — penyederhanaan yang sama dengan area_polygon.polygon (format lama, satu cincin).
     *
     * @param  list<int>  $idMk
     * @return list<array{id:int,name:?string,polygon:list<array{lat:float,lng:float}>}>
     */
    private static function ringkas(array $idMk): array
    {
        $hasil = [];
        foreach (WilayahKerjaSales::polygon($idMk) as $id => $p) {
            $multi = WilayahKerjaSales::multi($p) ?? [];
            $cincin = $multi ? Geometri::cincinTerbesar($multi) : [];

            if (count($cincin) < 3) {
                continue;
            }

            $hasil[] = [
                'id' => (int) $id,
                'name' => $p->name,
                'polygon' => array_map(fn ($titik) => ['lat' => $titik[1], 'lng' => $titik[0]], $cincin),
            ];
        }

        return $hasil;
    }
}
