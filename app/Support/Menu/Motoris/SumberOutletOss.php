<?php

namespace App\Support\Menu\Motoris;

/** Out of Stock / Lap. Outlet OSS: outlet AC yang pada kunjungan terakhir tanpa stok produk sendiri. */
class SumberOutletOss extends SumberOutletStatus
{
    protected string $mode = 'oss';
}
