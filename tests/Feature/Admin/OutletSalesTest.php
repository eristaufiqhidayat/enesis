<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;

class OutletSalesTest extends AdminTestCase
{
    public function test_outlet_di_bawah_sales_mengikuti_area_rute(): void
    {
        DB::table('mk_area')->insert([
            ['id' => 10, 'kode' => 'A10', 'nama' => 'Waru', 'depo_id' => 46],
            ['id' => 11, 'kode' => 'A11', 'nama' => 'Gedangan', 'depo_id' => 46],
            ['id' => 12, 'kode' => 'A12', 'nama' => 'Balaraja Kota', 'depo_id' => 22],
        ]);
        DB::table('mk_karyawan_area')->insert([
            ['karyawan_id' => 1, 'area_id' => 10, 'aktif' => true],
            ['karyawan_id' => 1, 'area_id' => 11, 'aktif' => false],
            // Area tidak aktif yang namanya di urutan awal (dulu memutus perulangan penanda rute).
            ['karyawan_id' => 1, 'area_id' => 12, 'aktif' => false],
            ['karyawan_id' => 2, 'area_id' => 12, 'aktif' => false],
        ]);
        DB::table('mk_outlet')->insert([
            ['id' => 1, 'kode' => 'OT-1', 'nama' => 'Toko Makmur', 'area_id' => 10, 'kecamatan' => 'Waru', 'latitude' => -7.35, 'longitude' => 112.73],
            ['id' => 2, 'kode' => 'OT-2', 'nama' => 'Warung Bu Sri', 'area_id' => 10, 'kecamatan' => 'Waru', 'latitude' => null, 'longitude' => null],
            ['id' => 3, 'kode' => 'OT-3', 'nama' => 'Toko Gedangan', 'area_id' => 11, 'kecamatan' => 'Gedangan', 'latitude' => null, 'longitude' => null],
            ['id' => 4, 'kode' => 'OT-4', 'nama' => 'Toko Balaraja', 'area_id' => 12, 'kecamatan' => null, 'latitude' => null, 'longitude' => null],
        ]);
        $rudi = $this->akun('rudi', 'sales', 1);
        $sinta = $this->akun('sinta', 'sales', 2);

        // Hanya area aktif yang jadi rute (sama dengan aplikasi HP).
        $this->get('/user_sales/'.$rudi->id)->assertOk()
            ->assertSee('Outlet di bawah sales ini')->assertSee('2 outlet di 1 area rute')
            ->assertSee('Toko Makmur')->assertSee('Warung Bu Sri')
            ->assertDontSee('Toko Gedangan')->assertDontSee('Toko Balaraja')
            ->assertSee('Gedangan · 1 outlet · tidak aktif');

        // Cari outlet.
        $this->get('/user_sales/'.$rudi->id.'?cari_outlet=sri')->assertOk()
            ->assertSee('Warung Bu Sri')->assertDontSee('Toko Makmur');

        // Juga di halaman edit.
        $this->get('/user_sales/'.$rudi->id.'/edit')->assertOk()->assertSee('Toko Makmur');

        // Sales yang semua areanya tidak aktif: semua area jadi rute.
        $this->get('/user_sales/'.$sinta->id.'/edit')->assertOk()->assertSee('Toko Balaraja')->assertSee('1 outlet di 1 area rute');

        // Sales tanpa area.
        $this->get('/user_sales/'.$this->akun('tegar', 'sales', 3)->id)->assertOk()->assertSee('belum punya area');

        // Akun koordinator tidak menampilkan kartu outlet.
        $koor = $this->akun('koor.satu', 'koordinator', 137);
        $this->get('/user_koordinator/'.$koor->id)->assertOk()->assertDontSee('Outlet di bawah sales ini');
    }
}
