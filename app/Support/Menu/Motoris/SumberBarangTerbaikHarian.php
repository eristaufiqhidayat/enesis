<?php

namespace App\Support\Menu\Motoris;

/** Lap. Barang Terbaik Harian. */
class SumberBarangTerbaikHarian extends SumberBarangTerbaik
{
    protected function periode(): string
    {
        return 'harian';
    }
}
