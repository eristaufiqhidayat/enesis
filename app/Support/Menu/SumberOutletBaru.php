<?php

namespace App\Support\Menu;

use App\Models\User;
use App\Support\Akses\Akses;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Outlet baru yang didaftarkan sales lewat aplikasi mobile (trx_outlet_baru).
 * Yang masih menunggu verifikasi tampil paling atas, lalu yang terbaru. Baris dibatasi cakupan role
 * (depo / downline / diri) lewat sales yang mendaftarkan.
 */
class SumberOutletBaru implements SumberTabel
{
    public function tabelUtama(): string
    {
        return 'trx_outlet_baru';
    }

    public function query(?User $user): Builder
    {
        $query = DB::table('trx_outlet_baru as b')
            ->leftJoin('mk_karyawan as k', 'k.id', '=', 'b.karyawan_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'b.depo_id')
            ->leftJoin('mk_area as a', 'a.id', '=', 'b.area_id')
            ->select([
                'b.id', 'b.status', 'b.nama', 'b.pemilik', 'b.no_hp', 'b.tipe', 'b.alamat',
                'k.nama as sales', 'd.nama as depo', 'a.nama as area',
                'b.lat', 'b.lng', 'b.outlet_id', 'b.recorded_at', 'b.alasan_tolak',
                // Untuk tautan foto & tombol verifikasi, tidak tampil sebagai kolom.
                'b.foto_toko_id', 'b.foto_rak_id',
            ])
            ->orderByRaw("CASE WHEN b.status = 'menunggu' THEN 0 ELSE 1 END")
            ->orderByDesc('b.id');

        return Akses::batasiKaryawan($query, $user);
    }

    public function ubahBaris(object $baris): object
    {
        $baris->recorded_at = JamLokal::format($baris->recorded_at);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['b.id', 'b.status', 'b.nama', 'b.pemilik', 'b.no_hp', 'b.alamat', 'k.nama', 'd.nama', 'a.nama'];
    }
}
