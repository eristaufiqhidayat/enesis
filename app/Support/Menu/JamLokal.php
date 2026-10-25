<?php

namespace App\Support\Menu;

use App\Support\Mobile\Pengaturan;
use Carbon\CarbonImmutable;

/** Waktu transaksi HP disimpan UTC; di tabel web ditampilkan jam lokal (WIB). */
class JamLokal
{
    public static function format(mixed $nilai, string $pola = 'Y-m-d H:i'): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $nilai, 'UTC')->setTimezone(Pengaturan::zona())->format($pola);
    }
}
