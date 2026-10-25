<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Menu penjualan (Penjualan Barang, Lap. Efective Call, detail, barang terbaik) dari trx_penjualan.
class MenuPenjualanMotorisTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 7)->setTime(12, 0));

        DB::table('mk_outlet')->insert([['id' => 11, 'kode' => 'OUT-1', 'nama' => 'Toko Satu', 'area' => 'Sidoarjo-01']]);
        DB::table('mk_produk')->insert([
            ['id' => 3, 'kode' => 'SKU-3', 'nama' => 'Adem Sari', 'data' => json_encode(['merk' => 'Adem Sari', 'kategori' => 'Minuman', 'satuan' => 'Botol'])],
            ['id' => 4, 'kode' => 'SKU-4', 'nama' => 'Plossa', 'data' => null],
        ]);
        $kunjungan = DB::table('trx_kunjungan')->insertGetId(['karyawan_id' => 1, 'outlet_id' => 11, 'tanggal' => '2026-10-06',
            'checkin_at' => '2026-10-06 02:00:00', 'checkout_at' => '2026-10-06 02:10:00', 'durasi_detik' => 600]);
        $jual = fn ($kode, $karyawan, $tgl, $ec, $total, $item, $lain = []) => tap(DB::table('trx_penjualan')->insertGetId($lain + [
            'kode' => $kode, 'kunjungan_id' => $kunjungan, 'karyawan_id' => $karyawan, 'depo_id' => 46, 'outlet_id' => 11, 'tanggal' => $tgl,
            'jumlah_sku' => count($item), 'total_qty' => array_sum($item), 'total' => $total, 'is_effective_call' => $ec,
            'recorded_at' => "{$tgl} 02:05:00",
        ]), fn ($id) => DB::table('trx_penjualan_item')->insert(array_map(
            fn ($produk, $qty) => ['penjualan_id' => $id, 'produk_id' => $produk, 'qty' => $qty, 'harga' => 1000, 'total' => $qty * 1000],
            array_keys($item), $item,
        )));

        $jual('SLS-1', 1, '2026-10-06', true, 7000, [3 => 5, 4 => 2], ['sumber' => 'lama', 'sumber_id' => 1]);
        $jual('HP-2', 3, '2026-10-06', false, 1000, [4 => 1]);
        $jual('HP-3', 1, '2026-10-05', true, 3000, [3 => 3]);
        $jual('HP-4', 1, '2026-10-06', true, 9000, [3 => 9], ['dibatalkan_at' => '2026-10-06 03:00:00']); // batal
        $jual('HP-5', 2, '2026-10-06', true, 2000, [3 => 2]); // Balaraja
    }

    public function test_penjualan_barang_dari_trx_penjualan(): void
    {
        $this->get('/penjualan_barang')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.name', 'lap_penjualan_barang')
                ->where('tables.0.rows.total', 4) // yang dibatalkan tidak tampil
                ->where('tables.0.rows.data.0.trans_code', 'HP-5') // terbaru dulu
                ->where('tables.0.rows.data.2.trans_code', 'SLS-1')
                ->where('tables.0.rows.data.2.salesperson_name', 'Rudi Hartono')
                ->where('tables.0.rows.data.2.koordinator_name', 'Koor Satu')
                ->where('tables.0.rows.data.2.datetime_trans', '09:05:00')
                ->where('tables.0.rows.data.2.datetime_checkin', '09:00:00')
                ->where('tables.0.rows.data.2.effective_call_status', 'Ya')
                ->where('tables.0.rows.data.2.grand_total', '7.000')
                ->where('tables.0.rows.data.2.outlet_code', 'OUT-1')
                ->where('tables.0.rows.data.2.warehouse_name', 'Sidoarjo')
                ->where('tables.0.rows.data.2.sumber', 'Aplikasi lama')
                ->where('tables.0.rows.data.1.sumber', 'Aplikasi HP')
            )
        );

        $this->get('/lap_effective_call?q=OUT-1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 3) // HP-2 bukan EC
            ));
    }

    public function test_detail_produk_dan_ec_detail(): void
    {
        $this->get('/lap_penjualan_barang_detail?q=SLS-1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 2)
                ->where('tables.0.rows.data.0.product_sku', 'SKU-3')
                ->where('tables.0.rows.data.0.category_name', 'Minuman')
                ->where('tables.0.rows.data.0.unit_name', 'Botol')
                ->where('tables.0.rows.data.0.quantity', '5')
                ->where('tables.0.rows.data.0.total', '5.000')
            ));

        $this->get('/lap_effective_call_barang?q=Plossa')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 1) // Plossa di HP-2 bukan EC
            ));
    }

    public function test_barang_terbaik_per_periode(): void
    {
        $this->get('/lap_penjualan_barang_terbaik_harian?tanggal=2026-10-06')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 2)
                ->where('tables.0.rows.data.0.product_name', 'Adem Sari')
                ->where('tables.0.rows.data.0.total_qty', '7') // 5 (Rudi) + 2 (Sinta)
                ->where('tables.0.rows.data.0.total_transaksi', 2)
                ->where('tables.0.rows.data.0.tanggal_data', '2026-10-06')
                ->where('tables.0.kpi.1.value', 2)
            ));

        // Minggu 5–11 Okt memuat 5 & 6 Okt.
        $this->get('/lap_penjualan_barang_terbaik_mingguan?tanggal=2026-10-07')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.data.0.total_qty', '10')
            ));
        $this->get('/lap_penjualan_barang_terbaik_bulanan?tanggal=2026-10-20&depo=22')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.total_qty', '2')
            ));
    }

    public function test_cakupan_role_depo(): void
    {
        $staf = $this->akun('staf.balaraja', 'lainnya', 2, ['role_id' => $this->role('Admin Depo', 'depo', ['penjualan_barang' => ['lihat']])]);
        $this->actingAs($staf);

        $this->get('/penjualan_barang')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.trans_code', 'HP-5')
            ));
    }
}
