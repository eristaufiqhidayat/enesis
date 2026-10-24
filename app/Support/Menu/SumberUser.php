<?php

namespace App\Support\Menu;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

// Sumber data menu User Sales dari tabel login Enesis (users), bukan tabel salinan layar
// aplikasi lama (user_sales). Dibungkus subquery supaya MenuController bisa mencari dan
// mengurutkan memakai nama kolom tampilan (username, karyawan, depo, ...).
class SumberUser
{
    public static function sales(): Builder
    {
        return self::kategori('sales');
    }

    public static function kategori(string $kategori): Builder
    {
        $dasar = DB::table('users as u')
            ->leftJoin('mk_user as mu', function ($j) {
                $j->on('mu.jenis', '=', 'u.sumber_jenis')->on('mu.id', '=', 'u.sumber_id');
            })
            ->leftJoin('mk_karyawan as k', 'k.id', '=', 'u.karyawan_id')
            ->leftJoin('mk_depo as d', 'd.id', '=', 'k.depo_id')
            ->leftJoin('mk_karyawan as ko', 'ko.id', '=', 'k.koordinator_id')
            ->where('u.kategori', $kategori)
            ->selectRaw(implode(', ', [
                'u.id',
                'u.username',
                'mu.nickname',
                'COALESCE(k.nama, u.name) AS karyawan',
                'd.nama AS depo',
                'ko.nama AS koordinator',
                // Boolean dipakai langsung di CASE (tanpa "= 1") supaya jalan di SQLite, MySQL, PostgreSQL.
                "CASE WHEN u.aktif THEN 'Aktif' ELSE 'Tidak Aktif' END AS status",
                "CASE WHEN u.perlu_keputusan THEN 'Perlu keputusan admin' "
                ."WHEN u.karyawan_id IS NULL THEN 'Belum terhubung ke karyawan' "
                ."WHEN u.wajib_ganti_password THEN 'Belum ganti password awal' ELSE '' END AS keterangan",
                'u.login_terakhir_pada',
            ]));

        return DB::query()->fromSub($dasar, 'user_sales');
    }
}
