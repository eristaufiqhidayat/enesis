<?php

namespace App\Support\Menu\Motoris;

/** Tracking Last Seen Koordinator: posisi GPS terakhir tiap koordinator aktif. */
class SumberLastSeenKoordinator extends SumberLastSeen
{
    protected function kategori(): string
    {
        return 'koordinator';
    }
}
