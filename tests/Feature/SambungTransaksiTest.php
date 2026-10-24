<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Isi *_id di tabel transaksi lap_* dari master mk_* (data rekaan).
class SambungTransaksiTest extends TestCase
{
    use RefreshDatabase;

    private function isiMaster(): void
    {
        DB::table('mk_depo')->insert([['id' => 46, 'nama' => 'Sidoarjo'], ['id' => 22, 'nama' => 'Balaraja']]);
        DB::table('mk_area')->insert([['id' => 18685, 'nama' => 'Area Gresik-200']]);
        DB::table('mk_distributor')->insert([['id' => 7, 'nama' => 'PT Distributor Contoh']]);
        DB::table('mk_produk')->insert([['id' => 3, 'kode' => '8991001', 'nama' => 'Minuman 320 ml']]);
        DB::table('mk_outlet')->insert([['id' => 11, 'kode' => 'OUT-1', 'nama' => 'Toko Satu']]);
        DB::table('mk_karyawan')->insert([
            ['id' => 1, 'kategori' => 'sales', 'nama' => 'Andi', 'depo_id' => 46],
            ['id' => 2, 'kategori' => 'sales', 'nama' => 'Andi', 'depo_id' => 22], // nama kembar beda depo
            ['id' => 3, 'kategori' => 'sales', 'nama' => 'Budi', 'depo_id' => 46],
            ['id' => 137, 'kategori' => 'koordinator', 'nama' => 'Koor Satu', 'depo_id' => 46],
            ['id' => 138, 'kategori' => 'koordinator', 'nama' => 'Koor Kembar', 'depo_id' => 46],
            ['id' => 139, 'kategori' => 'koordinator', 'nama' => 'Koor Kembar', 'depo_id' => 22],
            ['id' => 140, 'kategori' => 'koordinator', 'nama' => 'Dulu Sales', 'depo_id' => 46], // kini koordinator
        ]);
    }

    public function test_mengisi_id_master_di_tabel_transaksi(): void
    {
        $this->isiMaster();

        $jual = fn ($kode, $sales, $depo, $outlet = 'OUT-1') => [
            'trans_code' => $kode, 'salesperson_name' => $sales, 'koordinator_name' => 'Koor Satu',
            'warehouse_name' => $depo, 'area_name' => 'Area Gresik-200', 'outlet_code' => $outlet,
        ];

        DB::table('lap_penjualan_barang')->insert([
            $jual('S1', 'Andi', 'Sidoarjo'),
            $jual('S2', 'Andi', 'Balaraja'),
            $jual('S3', 'budi ', ' SIDOARJO'),        // beda huruf/spasi tetap cocok
            $jual('S4', 'Orang Lain', 'Sidoarjo', 'OUT-TIDAK-ADA'),
            $jual('S5', '-', 'Sidoarjo'),
            ['koordinator_name' => 'Koor Kembar'] + $jual('S6', 'Dulu Sales', 'Balaraja'),
        ]);

        DB::table('lap_penjualan_barang_detail')->insert([
            ['trans_code' => 'S1', 'salesperson_name' => 'Andi', 'warehouse_name' => 'Sidoarjo', 'product_sku' => 8991001, 'outlet_code' => 'OUT-1'],
        ]);
        DB::table('lap_loading_barang')->insert([
            ['trans_code' => 'L1', 'warehouse_name' => 'Balaraja', 'koordinator_name' => 'Koor Satu', 'distributor_name' => 'pt distributor contoh'],
        ]);

        $this->artisan('enesis:sambung-transaksi')->assertSuccessful();

        $hasil = DB::table('lap_penjualan_barang')->orderBy('trans_code')->get()->keyBy('trans_code');
        $this->assertSame(1, (int) $hasil['S1']->karyawan_id);
        $this->assertSame(2, (int) $hasil['S2']->karyawan_id, 'nama kembar diselesaikan dengan depo');
        $this->assertSame(3, (int) $hasil['S3']->karyawan_id);
        $this->assertNull($hasil['S4']->karyawan_id);
        $this->assertNull($hasil['S5']->karyawan_id);

        $this->assertSame(46, (int) $hasil['S1']->depo_id);
        $this->assertSame(22, (int) $hasil['S2']->depo_id);
        $this->assertSame(137, (int) $hasil['S1']->koordinator_id);
        $this->assertSame(18685, (int) $hasil['S1']->area_id);
        $this->assertSame(11, (int) $hasil['S1']->outlet_id);
        $this->assertNull($hasil['S4']->outlet_id);

        // Koordinator kembar diselesaikan dengan depo; sales yang kini koordinator tetap ketemu.
        $this->assertSame(139, (int) $hasil['S6']->koordinator_id);
        $this->assertSame(140, (int) $hasil['S6']->karyawan_id);

        $detail = DB::table('lap_penjualan_barang_detail')->first();
        $this->assertSame(3, (int) $detail->produk_id, 'product_sku angka cocok ke produk.kode teks');

        $loading = DB::table('lap_loading_barang')->first();
        $this->assertSame(7, (int) $loading->distributor_id);
        $this->assertSame(22, (int) $loading->depo_id);

        // Teks asli tidak berubah.
        $this->assertSame('budi ', $hasil['S3']->salesperson_name);

        // Jalan ulang hasilnya sama.
        $this->artisan('enesis:sambung-transaksi', ['--tabel' => ['lap_penjualan_barang']])->assertSuccessful();
        $this->assertSame(2, (int) DB::table('lap_penjualan_barang')->where('trans_code', 'S2')->value('karyawan_id'));
    }

    public function test_tanpa_master_gagal(): void
    {
        $this->artisan('enesis:sambung-transaksi')->assertFailed();
    }
}
