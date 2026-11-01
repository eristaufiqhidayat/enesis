<?php

namespace App\Support\Menu;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rekap Outlet Ditandai: semua tanda outlet (tanda_outlet, diberikan dari drawer Riwayat Lacak Motoris atau menu All Outlet),
 * hanya yang masih aktif (yang dicabut tidak ditampilkan). Tanda melekat ke outlet, bukan ke motoris, jadi tidak ada pembatasan cakupan
 * karyawan (sama seperti master outlet).
 */
class SumberTandaOutlet implements SumberTabel
{
    public function tabelUtama(): string
    {
        return 'tanda_outlet';
    }

    public function query(?User $user): Builder
    {
        // Motoris pendaftar (NOO): outlet dari HP lewat trx_outlet_baru.karyawan_id, outlet master lewat nama dibuat_oleh.
        $pendaftar = DB::table('trx_outlet_baru as ob')->join('mk_karyawan as kb', 'kb.id', '=', 'ob.karyawan_id')
            ->whereColumn('ob.outlet_id', 'o.id')->orderBy('ob.id')->limit(1)->select('kb.nama');

        // Depo: dari area outlet bila ada; area master sering kosong, jadi cadangannya depo motoris pendaftar.
        // Koordinator juga koordinator motoris pendaftar.
        $pendaftarKaryawan = fn () => DB::table('mk_karyawan as kp')
            ->where(fn ($w) => $w->whereExists(fn ($e) => $e->selectRaw('1')->from('trx_outlet_baru as ob2')
                ->whereColumn('ob2.outlet_id', 'o.id')->whereColumn('ob2.karyawan_id', 'kp.id'))
                ->orWhereColumn('kp.nama', 'o.dibuat_oleh'))
            ->orderBy('kp.id')->limit(1);
        $depoPendaftar = $pendaftarKaryawan()->join('mk_depo as dp', 'dp.id', '=', 'kp.depo_id')->select('dp.nama');
        $koordinatorPendaftar = $pendaftarKaryawan()->join('mk_karyawan as kor', 'kor.id', '=', 'kp.koordinator_id')->select('kor.nama');

        return DB::table('tanda_outlet as t')
            ->whereNull('t.dicabut_at')
            ->join('mk_outlet as o', 'o.id', '=', 't.outlet_id')
            ->leftJoin('mk_area as a', 'a.id', '=', 'o.area_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'a.depo_id')
            ->select([
                't.id', 't.outlet_id', 'o.nama as outlet',
                DB::raw('COALESCE(a.nama, o.area) AS area'),
                't.kategori', 't.dibuat_nama as ditandai_oleh', 't.created_at', 't.dicabut_at',
            ])
            ->selectSub($pendaftar, 'motoris_noo')
            ->addSelect(DB::raw('COALESCE(d.nama, ('.$depoPendaftar->toSql().')) AS depo'))
            ->addBinding($depoPendaftar->getBindings(), 'select')
            ->selectSub($koordinatorPendaftar, 'koordinator')
            ->orderByDesc('t.created_at')->orderByDesc('t.id');
    }

    public function ubahBaris(object $baris): object
    {
        $baris->motoris_noo = $baris->motoris_noo ?: DB::table('mk_outlet')->where('id', $baris->outlet_id)->value('dibuat_oleh');
        $baris->kategori = config("marketama.tanda_outlet.{$baris->kategori}", $baris->kategori);
        $baris->ditandai_pada = JamLokal::format($baris->created_at, 'j M Y H:i', 'id');
        unset($baris->created_at, $baris->dicabut_at);

        return $baris;
    }

    public function kolomCari(): array
    {
        return ['o.nama', 'a.nama', 'o.area', 'd.nama', 'o.dibuat_oleh', 't.dibuat_nama'];
    }
}
