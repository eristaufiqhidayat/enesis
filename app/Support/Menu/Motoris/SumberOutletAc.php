<?php

namespace App\Support\Menu\Motoris;

/** Actual Call / Lap. Outlet AC: outlet yang dikunjungi tetapi belum pernah effective call. */
class SumberOutletAc extends SumberOutletStatus
{
    protected string $mode = 'ac';
}
