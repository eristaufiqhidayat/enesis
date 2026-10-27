<?php

namespace App\Support\Wilayah;

// Hitungan geometri sederhana untuk GeoJSON MultiPolygon berkoordinat [lng, lat].
// Luas memakai proyeksi equirectangular per polygon: cukup teliti untuk wilayah selebar kota (< 0,5% selisih).
class Geometri
{
    private const R_BUMI_KM = 6371.0088;

    /**
     * Ubah path dataset cahyadsn/wilayah_boundaries ([lat, lng], kedalaman 3 = satu polygon dengan
     * cincin-cincinnya, kedalaman 4 = banyak polygon) menjadi koordinat MultiPolygon GeoJSON [lng, lat].
     */
    public static function dariPathLatLng(array $path): array
    {
        $polygons = self::kedalaman($path) >= 4 ? $path : [$path];

        $hasil = [];
        foreach ($polygons as $polygon) {
            $cincin = [];
            foreach ($polygon as $ring) {
                $titik = array_map(fn ($p) => [round((float) $p[1], 6), round((float) $p[0], 6)], $ring);
                $titik = self::tutup($titik);
                if (count($titik) >= 4) {
                    $cincin[] = $titik;
                }
            }
            if ($cincin) {
                $hasil[] = $cincin;
            }
        }

        return $hasil;
    }

    /** Terima GeoJSON Polygon/MultiPolygon (array), kembalikan koordinat MultiPolygon. */
    public static function multi(array $geojson): array
    {
        return match ($geojson['type'] ?? null) {
            'Polygon' => [$geojson['coordinates']],
            'MultiPolygon' => $geojson['coordinates'],
            default => [],
        };
    }

    public static function jumlahTitik(array $multi): int
    {
        $n = 0;
        foreach ($multi as $polygon) {
            foreach ($polygon as $ring) {
                $n += max(0, count($ring) - 1);
            }
        }

        return $n;
    }

    public static function luasKm2(array $multi): float
    {
        $total = 0.0;
        foreach ($multi as $polygon) {
            $total += self::luasPolygon($polygon);
        }

        return $total;
    }

    /**
     * Cincin luar polygon terluas, tanpa titik penutup, untuk kolom area_polygon.polygon
     * (format lama: satu cincin [lng, lat]). Dipilih berdasarkan luas, bukan jumlah titik.
     */
    public static function cincinTerbesar(array $multi): array
    {
        $terbaik = null;
        $luasTerbaik = -1.0;
        foreach ($multi as $polygon) {
            $luas = self::luasCincin($polygon[0] ?? []);
            if ($luas > $luasTerbaik) {
                $luasTerbaik = $luas;
                $terbaik = $polygon[0];
            }
        }

        if (! $terbaik) {
            return [];
        }
        array_pop($terbaik);

        return array_values($terbaik);
    }

    /** Pusat kotak batas (bounding box) [lat, lng], dipakai bila dataset tidak memberi centroid. */
    public static function pusatKotak(array $multi): ?array
    {
        $lat = [];
        $lng = [];
        foreach ($multi as $polygon) {
            foreach ($polygon[0] ?? [] as [$x, $y]) {
                $lng[] = $x;
                $lat[] = $y;
            }
        }

        return $lat ? [(min($lat) + max($lat)) / 2, (min($lng) + max($lng)) / 2] : null;
    }

    private static function luasPolygon(array $polygon): float
    {
        $luas = 0.0;
        foreach ($polygon as $i => $ring) {
            $luas += ($i === 0 ? 1 : -1) * self::luasCincin($ring);
        }

        return max(0.0, $luas);
    }

    private static function luasCincin(array $ring): float
    {
        $n = count($ring);
        if ($n < 4) {
            return 0.0;
        }

        $latTengah = deg2rad(array_sum(array_column($ring, 1)) / $n);
        $kx = self::R_BUMI_KM * cos($latTengah) * M_PI / 180;
        $ky = self::R_BUMI_KM * M_PI / 180;

        $jumlah = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            $jumlah += ($ring[$i][0] * $kx) * ($ring[$i + 1][1] * $ky) - ($ring[$i + 1][0] * $kx) * ($ring[$i][1] * $ky);
        }

        return abs($jumlah) / 2;
    }

    private static function tutup(array $titik): array
    {
        if ($titik && $titik[0] !== end($titik)) {
            $titik[] = $titik[0];
        }

        return $titik;
    }

    private static function kedalaman(mixed $x): int
    {
        $d = 0;
        while (is_array($x) && $x !== []) {
            $d++;
            $x = $x[0];
        }

        return $d;
    }
}
