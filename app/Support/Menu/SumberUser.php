<?php

namespace App\Support\Menu;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

// Sumber data menu User Sales / User Koordinator / User Lainnya / Super Admin dari tabel
// login Enesis (users), bukan tabel salinan layar aplikasi lama. Dibungkus subquery supaya
// MenuController bisa mencari dan mengurutkan memakai nama kolom tampilan.
class SumberUser
{
    public static function sales(): Builder
    {
        return self::kategori('sales');
    }

    public static function koordinator(): Builder
    {
        return self::kategori('koordinator');
    }

    public static function lainnya(): Builder
    {
        return self::kategori('lainnya');
    }

    public static function superAdmin(): Builder
    {
        return self::kategori('super_admin');
    }

    public static function kategori(string $kategori): Builder
    {
        // Akun super admin boleh tanpa karyawan (mis. akun "admin" buatan Enesis).
        $tanpaKaryawan = $kategori === 'super_admin' ? '' : "WHEN u.karyawan_id IS NULL THEN 'Belum terhubung ke karyawan' ";

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
                // Kategori user di aplikasi lama (mis. Admin Depo) dari baris asli layar.
                self::dariData('kategori').' AS kategori_user',
                'COALESCE(k.nama, u.name) AS karyawan',
                'd.nama AS depo',
                'ko.nama AS koordinator',
                // Boolean dipakai langsung di CASE (tanpa "= 1") supaya jalan di SQLite, MySQL, PostgreSQL.
                "CASE WHEN u.aktif THEN 'Aktif' ELSE 'Tidak Aktif' END AS status",
                "CASE WHEN u.perlu_keputusan THEN 'Perlu keputusan admin' "
                .$tanpaKaryawan
                ."WHEN u.wajib_ganti_password THEN 'Belum ganti password awal' ELSE '' END AS keterangan",
                'u.login_terakhir_pada',
            ]));

        return DB::query()->fromSub($dasar, 'users_menu');
    }

    /** Ambil satu kunci teks dari kolom JSON mk_user.data, sesuai driver database. */
    private static function dariData(string $kunci): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "(mu.data::json ->> '{$kunci}')",
            'mysql', 'mariadb' => "JSON_UNQUOTE(JSON_EXTRACT(mu.data, '$.{$kunci}'))",
            default => "json_extract(mu.data, '$.{$kunci}')",
        };
    }
}
