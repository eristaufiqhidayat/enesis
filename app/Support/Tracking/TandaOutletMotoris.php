<?php

namespace App\Support\Tracking;

use App\Support\Mobile\Pengaturan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tanda outlet (tanda_outlet) aktif pada outlet yang DIDAFTARKAN seorang motoris, dalam jendela hari terakhir yang
 * sama dengan tanda motoris. Pendaftar outlet master (mk_outlet) hanya tersimpan sebagai nama (dibuat_oleh, dari
 * Marketama); outlet dari HP dikaitkan lewat trx_outlet_baru.karyawan_id. Keduanya dianggap milik motoris itu.
 */
class TandaOutletMotoris
{
    /** Awal jendela (UTC, sama dengan created_at). */
    public static function awalUtc(): string
    {
        $hari = (int) config('marketama.tanda_motoris_hari');

        return Pengaturan::sekarang()->subDays($hari - 1)->startOfDay()->utc()->format('Y-m-d H:i:s');
    }

    /**
     * @param  bool  $korelasi  true = $id dan $nama adalah nama kolom query luar (mis. 'k.id'); false = nilai langsung.
     */
    public static function aktif(bool $korelasi, int|string $id, ?string $nama): Builder
    {
        return DB::table('tanda_outlet as tout')
            ->join('mk_outlet as o', 'o.id', '=', 'tout.outlet_id')
            ->whereNull('tout.dicabut_at')
            ->where('tout.created_at', '>=', self::awalUtc())
            ->where(function (Builder $w) use ($korelasi, $id, $nama) {
                if ($korelasi) {
                    $w->whereColumn('o.dibuat_oleh', $nama);
                } elseif ($nama !== null && $nama !== '') {
                    $w->where('o.dibuat_oleh', $nama);
                } else {
                    $w->whereRaw('1 = 0');
                }

                $w->orWhereExists(function (Builder $e) use ($korelasi, $id) {
                    $e->selectRaw('1')->from('trx_outlet_baru as ob')->whereColumn('ob.outlet_id', 'o.id');
                    $korelasi ? $e->whereColumn('ob.karyawan_id', $id) : $e->where('ob.karyawan_id', $id);
                });
            });
    }
}
