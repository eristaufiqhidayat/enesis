<?php

namespace App\Support\Mobile;

// Hitungan jarak GPS (meter) tanpa fungsi spasial database, supaya sama di SQLite/MySQL/PostgreSQL.
class Lokasi
{
    public static function jarak(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(2 * $r * asin(min(1, sqrt($a))));
    }

    /**
     * Kotak lintang/bujur di sekitar titik (untuk saring awal pakai indeks sebelum hitung jarak).
     *
     * @return array{0: float, 1: float, 2: float, 3: float} [latMin, latMax, lngMin, lngMax]
     */
    public static function kotak(float $lat, float $lng, int $meter): array
    {
        $dLat = $meter / 111320;
        $dLng = $meter / (111320 * max(0.01, cos(deg2rad($lat))));

        return [$lat - $dLat, $lat + $dLat, $lng - $dLng, $lng + $dLng];
    }

    /** Koordinat dianggap ada bila bukan null/0 dan dalam rentang. */
    public static function sah(mixed $lat, mixed $lng): bool
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return false;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        return ($lat != 0.0 || $lng != 0.0) && abs($lat) <= 90 && abs($lng) <= 180;
    }
}
