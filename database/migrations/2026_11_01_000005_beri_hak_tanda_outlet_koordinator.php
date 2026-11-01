<?php

use App\Support\Akses\Akses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Tanda outlet memakai hak Tambah pada menu outlet_all. Role bawaan Koordinator yang sudah punya akses menu itu
// otomatis mendapat Tambah (menandai outlet dan mencabut tanda sendiri); admin tetap bisa mencabutnya dari menu Role.
return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('roles')->where('kode', 'koordinator')->where('bawaan', true)->pluck('id');

        DB::table('role_hak')->whereIn('role_id', $role)->where('menu', 'outlet_all')->where('lihat', true)->update(['tambah' => true]);
        Akses::lupakan();
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('kode', 'koordinator')->where('bawaan', true)->pluck('id');

        DB::table('role_hak')->whereIn('role_id', $role)->where('menu', 'outlet_all')->update(['tambah' => false]);
        Akses::lupakan();
    }
};
