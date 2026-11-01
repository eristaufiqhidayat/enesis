<?php

use App\Support\Akses\Akses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Rekap "Outlet Ditandai" (tab keempat Lacak Motoris) menampilkan data tanda outlet yang sama dengan drawer Tanda Outlet,
// jadi role yang sudah boleh membuka Tracking Last Seen Sales otomatis boleh melihat rekapnya (hanya Lihat).
// Admin tetap bisa mencabutnya dari menu Role.
return new class extends Migration
{
    public function up(): void
    {
        $sudah = DB::table('role_hak')->where('menu', 'outlet_ditandai')->pluck('role_id')->flip();

        foreach (DB::table('role_hak')->where('menu', 'tracking_sales')->where('lihat', true)->pluck('role_id') as $roleId) {
            if (! isset($sudah[$roleId])) {
                DB::table('role_hak')->insert([
                    'role_id' => $roleId, 'menu' => 'outlet_ditandai',
                    'lihat' => true, 'tambah' => false, 'ubah' => false, 'hapus' => false, 'ekspor' => false,
                ]);
            }
        }

        Akses::lupakan();
    }

    public function down(): void
    {
        DB::table('role_hak')->where('menu', 'outlet_ditandai')->delete();
        Akses::lupakan();
    }
};
