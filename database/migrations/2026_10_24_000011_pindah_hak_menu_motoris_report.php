<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Tiga menu motoris (motoris_coverage, motoris_performance, monitor_absensi_motoris) digabung jadi
// satu halaman bertab (motoris_report). Hak tiap role atas menu lama dipindah ke menu baru
// dengan digabung per aksi (ada salah satu yang boleh = boleh), supaya role tidak kehilangan akses.
return new class extends Migration
{
    private const MENU_LAMA = ['motoris_coverage', 'motoris_performance', 'monitor_absensi_motoris'];

    private const MENU_BARU = 'motoris_report';

    private const AKSI = ['lihat', 'tambah', 'ubah', 'hapus', 'ekspor'];

    public function up(): void
    {
        $perRole = DB::table('role_hak')->whereIn('menu', self::MENU_LAMA)->get()->groupBy('role_id');

        foreach ($perRole as $roleId => $baris) {
            $gabung = array_fill_keys(self::AKSI, false);

            foreach ($baris as $b) {
                foreach (self::AKSI as $aksi) {
                    $gabung[$aksi] = $gabung[$aksi] || (bool) $b->{$aksi};
                }
            }

            $ada = DB::table('role_hak')->where('role_id', $roleId)->where('menu', self::MENU_BARU)->first();

            if ($ada) {
                foreach (self::AKSI as $aksi) {
                    $gabung[$aksi] = $gabung[$aksi] || (bool) $ada->{$aksi};
                }

                DB::table('role_hak')->where('id', $ada->id)->update($gabung);
            } else {
                DB::table('role_hak')->insert(['role_id' => $roleId, 'menu' => self::MENU_BARU] + $gabung);
            }
        }

        DB::table('role_hak')->whereIn('menu', self::MENU_LAMA)->delete();
    }

    // Tidak bisa dibalik: setelah digabung, tidak ada cara tahu hak mana yang dulunya milik menu lama.
    public function down(): void
    {
        //
    }
};
