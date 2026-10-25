<?php

namespace App\Support\Menu\Motoris;

/** Pengembalian Barang / Lap. Pengembalian Barang: muatan canvassing yang sisanya sudah diserahkan kembali ke depo. */
class SumberPengembalianBarang extends SumberCanvassing
{
    protected string $mode = 'kembali';
}
