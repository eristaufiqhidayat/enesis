<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\JamLokal;
use App\Support\Menu\SumberTabel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * New Open Outlet / Lap. Outlet NOO: outlet baru yang didaftarkan sales lewat aplikasi HP (trx_outlet_baru), semua status
 * verifikasi; kode outlet terisi setelah disetujui (menu All Outlet › Outlet Baru dari HP). Dibatasi cakupan role lewat salesnya.
 */
class SumberOutletNoo implements SumberTabel
{
    private const STATUS = ['menunggu' => 'Menunggu verifikasi', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];

    public function tabelUtama(): string
    {
        return 'trx_outlet_baru';
    }

    public function query(?User $user): Builder
    {
        $q = DB::table('trx_outlet_baru as b')
            ->join('mk_karyawan as k', 'k.id', '=', 'b.karyawan_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->leftJoin('mk_area as a', 'a.id', '=', 'b.area_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'b.depo_id')
            ->leftJoin('mk_outlet as o', 'o.id', '=', 'b.outlet_id')
            ->orderByDesc('b.id')
            ->select([
                'b.id as source_id', 'o.kode as outlet_code', 'b.nama as outlet_name', 'b.pemilik as contact_person1', 'b.no_hp as phone1',
                'b.alamat as address', 'b.lat as location_lat', 'b.lng as location_long', 'a.nama as area_name',
                'o.provinsi as province_name', 'o.kota_kabupaten as city_name', 'o.kecamatan as district_name', 'o.kelurahan as village_name',
                'b.tipe as category_name', 'k.nama as created_by_name', 'b.recorded_at as created_at', 'd.nama as warehouse_name',
                'ko.nama as koordinator_name', 'b.status',
            ]);

        return Akses::batasiKaryawan($q, $user);
    }

    public function ubahBaris(object $baris): object
    {
        $baris->created_at = JamLokal::format($baris->created_at, 'Y-m-d H:i:s');
        $baris->status = self::STATUS[$baris->status] ?? $baris->status;

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['b.nama', 'b.pemilik', 'b.no_hp', 'b.alamat', 'o.kode', 'k.nama', 'ko.nama', 'a.nama', 'd.nama'];
    }
}
