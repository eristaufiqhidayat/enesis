<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

// Menu All Outlet membaca tabel yang sama dengan API mobile sales motoris (mk_outlet / trx_outlet_baru).
class OutletAllTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('mk_depo')->insert(['id' => 7, 'nama' => 'Depo Bekasi']);
        DB::table('mk_area')->insert(['id' => 70, 'nama' => 'Bekasi Timur 01', 'depo_id' => 7]);
        DB::table('mk_karyawan')->insert(['id' => 900, 'kategori' => 'sales', 'nama' => 'Budi Motoris', 'depo_id' => 7]);
    }

    public function test_tab_master_membaca_mk_outlet_dengan_nama_area_dan_depo(): void
    {
        DB::table('mk_outlet')->insert([
            ['id' => 2, 'kode' => 'OT-2', 'nama' => 'Toko Dua', 'area_id' => null, 'area' => '-'],
            ['id' => 1, 'kode' => 'OT-1', 'nama' => 'Toko Satu', 'area_id' => 70, 'area' => 'nama lama'],
        ]);
        // Data laporan lama tidak ikut tampil di menu ini.
        DB::table('lap_outlet_all')->insert(['source_id' => 99, 'outlet_name' => 'Dari Laporan']);

        $this->get('/outlet_all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Show')
                ->where('activeTab', 'master')
                ->where('page.same_as', null)
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->has('tables', 1)
                    ->where('tables.0.name', 'mk_outlet')
                    ->where('tables.0.rows.total', 2)
                    ->where('tables.0.rows.data.0.id', 1)
                    ->where('tables.0.rows.data.0.area', 'Bekasi Timur 01')
                    ->where('tables.0.rows.data.0.depo', 'Depo Bekasi')
                    // Outlet tanpa area tetap tampil, area "-" seperti aplikasi lama.
                    ->where('tables.0.rows.data.1.area', '-')
                    ->where('tables.0.rows.data.1.depo', null)
                )
            );
    }

    public function test_pencarian_master_juga_mencari_nama_depo(): void
    {
        DB::table('mk_outlet')->insert([
            ['id' => 1, 'nama' => 'Toko Satu', 'area_id' => 70],
            ['id' => 2, 'nama' => 'Toko Dua', 'area_id' => null],
        ]);

        $this->get('/outlet_all?q=bekasi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('tables.0.rows.total', 1)
                    ->where('tables.0.rows.data.0.nama', 'Toko Satu')
                )
            );
    }

    public function test_tab_outlet_baru_menampilkan_yang_menunggu_lebih_dulu(): void
    {
        $baris = ['karyawan_id' => 900, 'depo_id' => 7, 'area_id' => 70, 'lat' => -6.2, 'lng' => 107.0];
        DB::table('trx_outlet_baru')->insert([
            ['id' => 1, 'nama' => 'Warung Menunggu', 'status' => 'menunggu', 'outlet_id' => null] + $baris,
            ['id' => 2, 'nama' => 'Warung Disetujui', 'status' => 'disetujui', 'outlet_id' => 1] + $baris,
        ]);

        $this->get('/outlet_all?tab=baru')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'baru')
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->has('tables', 1)
                    ->where('tables.0.name', 'trx_outlet_baru')
                    ->where('tables.0.rows.total', 2)
                    ->where('tables.0.rows.data.0.nama', 'Warung Menunggu')
                    ->where('tables.0.rows.data.0.sales', 'Budi Motoris')
                    ->where('tables.0.rows.data.0.depo', 'Depo Bekasi')
                    ->where('tables.0.rows.data.0.area', 'Bekasi Timur 01')
                    ->where('tables.0.rows.data.1.outlet_id', 1)
                )
            );
    }

    public function test_menu_laporan_outlet_all_membaca_master_outlet(): void
    {
        DB::table('lap_outlet_all')->insert(['source_id' => 99, 'outlet_name' => 'Dari Laporan']);
        DB::table('mk_outlet')->insert(['id' => 1, 'kode' => 'OUT-1', 'nama' => 'Toko Satu', 'area_id' => 70]);

        $this->get('/lap_outlet_all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('tables.0.name', 'lap_outlet_all')
                    ->where('tables.0.rows.total', 1)
                    ->where('tables.0.rows.data.0.outlet_name', 'Toko Satu')
                    ->where('tables.0.rows.data.0.warehouse_name', 'Depo Bekasi')
                )
            );
    }
}
