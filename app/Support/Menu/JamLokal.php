<?php

namespace App\Support\Menu;

use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;

/** Waktu transaksi HP disimpan UTC; di tabel web ditampilkan jam lokal (WIB). */
class JamLokal
{
    public static function format(mixed $nilai, string $pola = 'Y-m-d H:i', ?string $bahasa = null): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        $waktu = CarbonImmutable::parse((string) $nilai, 'UTC')->setTimezone(Pengaturan::zona());

        // Dengan $bahasa, nama bulan/hari diterjemahkan (mis. 'id' → "2 Nov 2026 19:50").
        return $bahasa ? $waktu->locale($bahasa)->translatedFormat($pola) : $waktu->format($pola);
    }
}
