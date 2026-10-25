<?php

namespace App\Support\Menu\Motoris;

/** Lap. Barang Habis Terjual: per produk muatan canvassing dengan sisa, status habis/belum, dan % terjual. */
class SumberBarangHabisTerjual extends SumberCanvassing
{
    protected string $mode = 'habis';
}
