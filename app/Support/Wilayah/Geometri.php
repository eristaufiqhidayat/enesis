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

    /** Kotak batas [minLng, minLat, maxLng, maxLat]. */
    public static function kotak(array $multi): ?array
    {
        $b = [INF, INF, -INF, -INF];
        foreach ($multi as $polygon) {
            foreach ($polygon[0] ?? [] as [$x, $y]) {
                $b = [min($b[0], $x), min($b[1], $y), max($b[2], $x), max($b[3], $y)];
            }
        }

        return $b[0] === INF ? null : $b;
    }

    /**
     * Potong MultiPolygon dengan persegi sejajar sumbu (Sutherland–Hodgman per cincin, termasuk lubang).
     * Persegi selalu cembung, jadi luas hasilnya tepat; bila hasil sebenarnya terpisah, cincinnya bisa
     * tersambung garis nol-lebar di tepi persegi (tidak mengubah luas maupun uji titik di dalam).
     */
    public static function potongPersegi(array $multi, float $minX, float $minY, float $maxX, float $maxY): array
    {
        $hasil = [];
        foreach ($multi as $polygon) {
            $b = self::kotak([$polygon]);
            if (! $b || $b[2] < $minX || $b[0] > $maxX || $b[3] < $minY || $b[1] > $maxY) {
                continue;
            }
            // Seluruh polygon di dalam persegi: tidak perlu dipotong.
            if ($b[0] >= $minX && $b[2] <= $maxX && $b[1] >= $minY && $b[3] <= $maxY) {
                $hasil[] = $polygon;

                continue;
            }

            $cincin = [];
            foreach ($polygon as $i => $ring) {
                $potong = self::potongCincin($ring, $minX, $minY, $maxX, $maxY);
                if (count($potong) < 4 || self::luasCincin($potong) < 1e-9) {
                    if ($i === 0) {
                        continue 2; // cincin luar habis: polygon ini di luar persegi
                    }

                    continue;
                }
                $cincin[] = $potong;
            }
            $hasil[] = $cincin;
        }

        return $hasil;
    }

    /** Uji titik di dalam MultiPolygon (aturan genap-ganjil, lubang dihitung). */
    public static function dalam(float $x, float $y, array $multi): bool
    {
        foreach ($multi as $polygon) {
            $dalam = false;
            foreach ($polygon as $ring) {
                $n = count($ring);
                for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
                    [$xi, $yi] = $ring[$i];
                    [$xj, $yj] = $ring[$j];
                    if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-15) + $xi) {
                        $dalam = ! $dalam;
                    }
                }
            }
            if ($dalam) {
                return true;
            }
        }

        return false;
    }

    /**
     * Titik [lng, lat] yang pasti di dalam MultiPolygon (untuk label dan cek tumpang tindih): titik berat
     * cincin luar polygon terluas bila di dalam, selain itu titik tengah potongan garis horizontal.
     */
    public static function titikDalam(array $multi): ?array
    {
        $terluas = null;
        $luasTerbaik = -1.0;
        foreach ($multi as $polygon) {
            $luas = self::luasPolygon($polygon);
            if ($luas > $luasTerbaik) {
                [$luasTerbaik, $terluas] = [$luas, $polygon];
            }
        }
        if (! $terluas) {
            return null;
        }

        $ring = $terluas[0];
        $n = count($ring) - 1;
        $tengah = [array_sum(array_column(array_slice($ring, 0, $n), 0)) / $n, array_sum(array_column(array_slice($ring, 0, $n), 1)) / $n];
        if (self::dalam($tengah[0], $tengah[1], [$terluas])) {
            return $tengah;
        }

        // Beberapa garis horizontal: ambil titik tengah segmen dalam yang terpanjang. Hasil potong
        // persegi bisa berupa dua bagian yang tersambung garis nol-lebar, jadi satu garis saja tidak cukup.
        $b = self::kotak([$terluas]);
        $terbaik = null;
        foreach ([0.5, 0.3, 0.7, 0.15, 0.85, 0.4, 0.6] as $t) {
            $y = $b[1] + ($b[3] - $b[1]) * $t;
            $xs = [];
            foreach ($terluas as $r) {
                for ($i = 0; $i < count($r) - 1; $i++) {
                    [$x1, $y1] = $r[$i];
                    [$x2, $y2] = $r[$i + 1];
                    if (($y1 > $y) !== ($y2 > $y)) {
                        $xs[] = $x1 + ($y - $y1) * ($x2 - $x1) / ($y2 - $y1);
                    }
                }
            }
            sort($xs);
            for ($i = 0; $i + 1 < count($xs); $i += 2) {
                if (! $terbaik || $terbaik[2] < $xs[$i + 1] - $xs[$i]) {
                    $terbaik = [($xs[$i] + $xs[$i + 1]) / 2, $y, $xs[$i + 1] - $xs[$i]];
                }
            }
        }

        return $terbaik ? [$terbaik[0], $terbaik[1]] : [$ring[0][0], $ring[0][1]];
    }

    private static function potongCincin(array $ring, float $minX, float $minY, float $maxX, float $maxY): array
    {
        // Buang titik penutup, potong terhadap 4 sisi, lalu tutup lagi.
        $titik = array_slice($ring, 0, -1);
        $sisi = [
            [fn ($p) => $p[0] >= $minX, fn ($a, $b) => self::potongX($a, $b, $minX)],
            [fn ($p) => $p[0] <= $maxX, fn ($a, $b) => self::potongX($a, $b, $maxX)],
            [fn ($p) => $p[1] >= $minY, fn ($a, $b) => self::potongY($a, $b, $minY)],
            [fn ($p) => $p[1] <= $maxY, fn ($a, $b) => self::potongY($a, $b, $maxY)],
        ];

        foreach ($sisi as [$di, $titikPotong]) {
            if (! $titik) {
                return [];
            }
            $keluar = [];
            $prev = end($titik);
            foreach ($titik as $cur) {
                $curDi = $di($cur);
                $prevDi = $di($prev);
                if ($curDi) {
                    if (! $prevDi) {
                        $keluar[] = $titikPotong($prev, $cur);
                    }
                    $keluar[] = $cur;
                } elseif ($prevDi) {
                    $keluar[] = $titikPotong($prev, $cur);
                }
                $prev = $cur;
            }
            $titik = $keluar;
        }

        if (count($titik) < 3) {
            return [];
        }
        $titik = array_map(fn ($p) => [round($p[0], 7), round($p[1], 7)], $titik);

        return self::tutup($titik);
    }

    private static function potongX(array $a, array $b, float $x): array
    {
        $t = ($x - $a[0]) / ($b[0] - $a[0]);

        return [$x, $a[1] + $t * ($b[1] - $a[1])];
    }

    private static function potongY(array $a, array $b, float $y): array
    {
        $t = ($y - $a[1]) / ($b[1] - $a[1]);

        return [$a[0] + $t * ($b[0] - $a[0]), $y];
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

        // Koordinat digeser ke titik pertama supaya perkalian tetap kecil (presisi untuk pecahan kecil).
        [$x0, $y0] = $ring[0];
        $jumlah = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            $jumlah += (($ring[$i][0] - $x0) * $kx) * (($ring[$i + 1][1] - $y0) * $ky)
                - (($ring[$i + 1][0] - $x0) * $kx) * (($ring[$i][1] - $y0) * $ky);
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
