<?php

namespace App\Support\Akses;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Koordinator sales yang diatur dari Enesis (tabel hierarki_sales). Nilainya ditulis langsung ke
// mk_karyawan.koordinator_id supaya semua fitur (daftar akun, cakupan downline, tracking, profil
// HP) ikut berubah, dan dipasang ulang setelah data karyawan ditarik dari aplikasi lama.
class HierarkiSales
{
    public static function atur(int $karyawanId, ?int $koordinatorId, ?int $olehUserId): void
    {
        DB::transaction(function () use ($karyawanId, $koordinatorId, $olehUserId) {
            $ada = DB::table('hierarki_sales')->where('karyawan_id', $karyawanId)->exists();
            $data = ['koordinator_id' => $koordinatorId, 'diubah_oleh' => $olehUserId, 'updated_at' => now()];

            $ada
                ? DB::table('hierarki_sales')->where('karyawan_id', $karyawanId)->update($data)
                : DB::table('hierarki_sales')->insert($data + ['karyawan_id' => $karyawanId, 'created_at' => now()]);

            DB::table('mk_karyawan')->where('id', $karyawanId)->update(['koordinator_id' => $koordinatorId]);
        });
    }

    /** Pasang ulang semua pengaturan Enesis ke mk_karyawan (setelah marketama:tarik). */
    public static function terapkanUlang(): int
    {
        if (! Schema::hasTable('hierarki_sales')) {
            return 0;
        }

        $jumlah = 0;

        DB::table('hierarki_sales')->orderBy('karyawan_id')->chunk(500, function ($baris) use (&$jumlah) {
            foreach ($baris as $h) {
                $jumlah += DB::table('mk_karyawan')->where('id', $h->karyawan_id)->update(['koordinator_id' => $h->koordinator_id]);
            }
        });

        return $jumlah;
    }
}
