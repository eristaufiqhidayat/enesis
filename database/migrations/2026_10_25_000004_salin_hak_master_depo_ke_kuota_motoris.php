<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menu baru Master Data › Kuota Motoris: role yang punya hak atas Master Warehouse / Depo mendapat hak
// yang sama, supaya admin yang biasa mengatur depo langsung bisa mengisi kuota. Super Admin selalu penuh.
return new class extends Migration
{
    private const AKSI = ['lihat', 'tambah', 'ubah', 'hapus', 'ekspor'];

    public function up(): void
    {
        foreach (DB::table('role_hak')->where('menu', 'master_depo')->get() as $b) {
            $ada = DB::table('role_hak')->where('role_id', $b->role_id)->where('menu', 'kuota_motoris')->exists();

            if (! $ada) {
                DB::table('role_hak')->insert(['role_id' => $b->role_id, 'menu' => 'kuota_motoris']
                    + collect(self::AKSI)->mapWithKeys(fn ($a) => [$a => (bool) $b->{$a}])->all());
            }
        }
    }

    public function down(): void
    {
        DB::table('role_hak')->where('menu', 'kuota_motoris')->delete();
    }
};
