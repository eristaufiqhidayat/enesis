<?php

namespace App\Support\Menu\Motoris;

/** Lap. Barang Terbaik Mingguan. */
class SumberBarangTerbaikMingguan extends SumberBarangTerbaik
{
    protected function periode(): string
    {
        return 'mingguan';
    }
}
