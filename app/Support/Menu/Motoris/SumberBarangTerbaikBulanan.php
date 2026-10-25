<?php

namespace App\Support\Menu\Motoris;

/** Lap. Barang Terbaik Bulanan. */
class SumberBarangTerbaikBulanan extends SumberBarangTerbaik
{
    protected function periode(): string
    {
        return 'bulanan';
    }
}
