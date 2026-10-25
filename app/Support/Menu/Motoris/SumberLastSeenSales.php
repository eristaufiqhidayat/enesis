<?php

namespace App\Support\Menu\Motoris;

/** Tracking Last Seen Sales: posisi GPS terakhir tiap sales aktif. */
class SumberLastSeenSales extends SumberLastSeen
{
    protected function kategori(): string
    {
        return 'sales';
    }
}
