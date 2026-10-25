<?php

namespace App\Support\Menu\Motoris;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Menu\SumberTabel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Menu outlet dari master outlet (mk_outlet) + aktivitas motoris (trx_kunjungan, trx_penjualan, trx_stok_outlet):
 * - semua: Lap. Outlet All;
 * - ec: outlet yang pernah transaksi effective call;
 * - ac: outlet yang pernah dikunjungi tetapi belum pernah effective call (sama dengan Marketama: EC tidak ikut di AC);
 * - oss: outlet AC yang pada kunjungan terakhir tidak tercatat stok produk sendiri (> 0) — *(konfirmasi)*;
 * - ro: outlet dengan effective call di dua tanggal berbeda atau lebih (repeat order) — *(konfirmasi)*.
 * Akun dengan cakupan selain "semua" hanya melihat outlet di area depo cakupannya.
 */
class SumberOutletStatus implements SumberTabel
{
    /** semua | ec | ac | oss | ro */
    protected string $mode = 'semua';

    public function tabelUtama(): string
    {
        return 'mk_outlet';
    }

    public function query(?User $user): Builder
    {
        $ec = DB::table('trx_penjualan')->whereNull('dibatalkan_at')->where('is_effective_call', true)->whereNotNull('outlet_id');
        $q = DB::table('mk_outlet as o')
            ->leftJoin('mk_area as a', 'a.id', '=', 'o.area_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'a.depo_id');

        match ($this->mode) {
            'ec' => $q->whereIn('o.id', (clone $ec)->select('outlet_id')),
            'ac', 'oss' => $q->whereIn('o.id', DB::table('trx_kunjungan')->whereNotNull('outlet_id')->select('outlet_id'))
                ->whereNotIn('o.id', (clone $ec)->select('outlet_id')),
            'ro' => $q->whereIn('o.id', (clone $ec)->groupBy('outlet_id')->havingRaw('COUNT(DISTINCT tanggal) >= 2')->select('outlet_id')),
            default => null,
        };

        if ($this->mode === 'oss') {
            // Kunjungan terakhir tiap outlet; OOS bila kunjungan itu tidak punya stok produk sendiri > 0.
            $terakhir = DB::table('trx_kunjungan')->whereNotNull('outlet_id')->groupBy('outlet_id')->selectRaw('outlet_id, MAX(id) AS id');
            $q->joinSub($terakhir, 'kt', 'kt.outlet_id', '=', 'o.id')
                ->whereNotExists(fn ($w) => $w->from('trx_stok_outlet as s')->whereColumn('s.kunjungan_id', 'kt.id')
                    ->whereNotNull('s.produk_id')->where('s.qty', '>', 0)->selectRaw('1'));
        }

        $depo = self::depoCakupan($user);

        return $q->when($depo !== null, fn ($w) => $w->whereIn('a.depo_id', $depo ?: [0]))
            ->orderByDesc('o.id')
            ->select([
                'o.id as source_id', 'o.kode as outlet_code', 'o.nama as outlet_name', 'o.alamat as address',
                'o.latitude as location_lat', 'o.longitude as location_long', DB::raw('COALESCE(a.nama, o.area) AS area_name'),
                'o.provinsi as province_name', 'o.kota_kabupaten as city_name', 'o.kecamatan as district_name', 'o.kelurahan as village_name',
                'o.dibuat_oleh as created_by_name', 'o.dibuat_pada as created_at', 'd.nama as warehouse_name', 'o.status_fiktif', 'o.verifikasi',
            ]);
    }

    public function ubahBaris(object $baris): object
    {
        // Kolom laporan RO lama memakai nama Indonesia.
        if ($this->mode === 'ro') {
            foreach (['depo' => 'warehouse_name', 'kode_outlet' => 'outlet_code', 'nama_outlet' => 'outlet_name', 'alamat' => 'address',
                'area' => 'area_name', 'provinsi' => 'province_name', 'kota_kab' => 'city_name', 'kecamatan' => 'district_name',
                'kelurahan' => 'village_name', 'latitude' => 'location_lat', 'longitude' => 'location_long',
                'dibuat_oleh' => 'created_by_name', 'dibuat_pada' => 'created_at'] as $baru => $lama) {
                $baris->{$baru} = $baris->{$lama};
            }
        }

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['o.kode', 'o.nama', 'o.alamat', 'o.area', 'a.nama', 'd.nama', 'o.kota_kabupaten', 'o.kecamatan', 'o.kelurahan'];
    }

    /** @return array<int>|null depo dalam cakupan akun; null = semua depo */
    public static function depoCakupan(?User $user): ?array
    {
        if (! $user || Akses::cakupan($user) === 'semua') {
            return null;
        }

        return Akses::batasiKaryawan(DB::table('mk_karyawan as k')->whereNotNull('k.depo_id'), $user)
            ->distinct()->pluck('k.depo_id')->map(fn ($id) => (int) $id)->all();
    }
}
