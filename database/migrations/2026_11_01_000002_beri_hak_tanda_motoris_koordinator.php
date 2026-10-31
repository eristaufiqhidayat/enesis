<?php

use App\Support\Akses\Akses;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Tanda motoris memakai hak Tambah pada menu tracking_sales. Role bawaan Koordinator yang sudah punya akses menu itu
// otomatis mendapat Tambah (memberi tanda dan mencabut tanda sendiri); admin tetap bisa mencabutnya dari menu Role.
return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('roles')->where('kode', 'koordinator')->where('bawaan', true)->pluck('id');

        DB::table('role_hak')->whereIn('role_id', $role)->where('menu', 'tracking_sales')->where('lihat', true)->update(['tambah' => true]);
        Akses::lupakan();
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('kode', 'koordinator')->where('bawaan', true)->pluck('id');

        DB::table('role_hak')->whereIn('role_id', $role)->where('menu', 'tracking_sales')->update(['tambah' => false]);
        Akses::lupakan();
    }
};
