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
        $outlet = $this->inertiaProps($this->get('/user_sales/'.$rudi->id), 'Akun/Show')['outletSales'];
        $this->assertSame(1, collect($outlet['area'])->where('rute', true)->count());
        $this->assertSame(2, $outlet['outlet']['total']);
        $this->assertSame(['Toko Makmur', 'Warung Bu Sri'], collect($outlet['outlet']['data'])->pluck('nama')->all());
        $gedangan = collect($outlet['area'])->firstWhere('nama', 'Gedangan');
        $this->assertSame(1, $gedangan['jumlahOutlet']);
        $this->assertFalse($gedangan['rute']);

        // Cari outlet.
        $cari = $this->inertiaProps($this->get('/user_sales/'.$rudi->id.'?cari_outlet=sri'), 'Akun/Show')['outletSales'];
        $this->assertSame(['Warung Bu Sri'], collect($cari['outlet']['data'])->pluck('nama')->all());

        // Juga di halaman edit.
        $edit = $this->inertiaProps($this->get('/user_sales/'.$rudi->id.'/edit'), 'Akun/Form')['outletSales'];
        $this->assertContains('Toko Makmur', collect($edit['outlet']['data'])->pluck('nama')->all());

        // Sales yang semua areanya tidak aktif: semua area jadi rute.
        $sintaOutlet = $this->inertiaProps($this->get('/user_sales/'.$sinta->id.'/edit'), 'Akun/Form')['outletSales'];
        $this->assertSame(['Toko Balaraja'], collect($sintaOutlet['outlet']['data'])->pluck('nama')->all());
        $this->assertSame(1, collect($sintaOutlet['area'])->where('rute', true)->count());

        // Sales tanpa area.
        $tegar = $this->akun('tegar', 'sales', 3);
        $this->assertSame([], $this->inertiaProps($this->get('/user_sales/'.$tegar->id), 'Akun/Show')['outletSales']['area']);

        // Akun koordinator tidak menampilkan kartu outlet.
        $koor = $this->akun('koor.satu', 'koordinator', 137);
        $this->assertNull($this->inertiaProps($this->get('/user_koordinator/'.$koor->id), 'Akun/Show')['outletSales']);
    }
}
