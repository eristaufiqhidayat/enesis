<?php

namespace App\Support\Menu;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Master outlet (mk_outlet) — tabel yang sama dengan yang dibaca API mobile sales motoris.
 * Nama area dan depo diambil dari mk_area / mk_depo lewat area_id.
 */
class SumberOutletMaster implements SumberTabel
{
    public function tabelUtama(): string
    {
        return 'mk_outlet';
    }

    public function query(?User $user): Builder
    {
        return DB::table('mk_outlet as o')
            ->leftJoin('mk_area as a', 'a.id', '=', 'o.area_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'a.depo_id')
            ->select([
                'o.id', 'o.kode', 'o.nama', 'o.alamat',
                // Area dari master; 8.751 outlet memang tanpa area (tampil "-" seperti di aplikasi lama).
                DB::raw('COALESCE(a.nama, o.area) AS area'),
                'd.nama as depo',
                'o.provinsi', 'o.kota_kabupaten', 'o.kecamatan', 'o.kelurahan',
                'o.latitude', 'o.longitude', 'o.verifikasi', 'o.status_fiktif',
                'o.dibuat_oleh', 'o.dibuat_pada',
            ])
            ->orderBy('o.id');
    }

    public function ubahBaris(object $baris): object
    {
        return $baris;
    }

    public function kolomCari(): array
    {
        return ['o.id', 'o.kode', 'o.nama', 'o.alamat', 'o.area', 'a.nama', 'd.nama', 'o.kota_kabupaten', 'o.kecamatan', 'o.kelurahan'];
    }
}
