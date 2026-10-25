<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Menu stok outlet/kompetitor dan canvassing/pengembalian dari tabel motoris.
class MenuStokCanvassingMotorisTest extends AdminTestCase
{
    private int $kunjungan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 7)->setTime(12, 0));

        DB::table('mk_outlet')->insert([['id' => 11, 'kode' => 'OUT-1', 'nama' => 'Toko Satu', 'area' => 'Sidoarjo-01']]);
        DB::table('mk_produk')->insert([
            ['id' => 3, 'kode' => 'SKU-3', 'nama' => 'Adem Sari', 'data' => json_encode(['merk' => 'Adem Sari', 'kategori' => 'Minuman', 'satuan' => 'Botol'])],
            ['id' => 4, 'kode' => 'SKU-4', 'nama' => 'Plossa', 'data' => null],
        ]);
        $this->kunjungan = DB::table('trx_kunjungan')->insertGetId(['karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 11, 'tanggal' => '2026-10-06',
            'checkin_at' => '2026-10-06 02:00:00', 'checkout_at' => '2026-10-06 02:10:00', 'durasi_detik' => 600]);
    }

    public function test_stok_outlet_dan_kompetitor(): void
    {
        $tutup = DB::table('trx_kunjungan')->insertGetId(['karyawan_id' => 3, 'depo_id' => 46, 'outlet_id' => 11, 'tanggal' => '2026-10-05',
            'checkin_at' => '2026-10-05 02:00:00', 'tidak_beli_kategori' => 'toko_tutup']);
        DB::table('trx_penjualan')->insert(['kode' => 'HP-1', 'kunjungan_id' => $this->kunjungan, 'karyawan_id' => 1, 'tanggal' => '2026-10-06',
            'jumlah_sku' => 1, 'total_qty' => 1, 'total' => 1000, 'is_effective_call' => true]);
        DB::table('trx_stok_outlet')->insert([
            ['kunjungan_id' => $this->kunjungan, 'produk_id' => 3, 'produk_kompetitor' => null, 'qty' => 4, 'created_at' => '2026-10-06 02:05:00'],
            ['kunjungan_id' => $this->kunjungan, 'produk_id' => null, 'produk_kompetitor' => 'Larutan Kaki Tiga', 'qty' => 6, 'created_at' => '2026-10-06 02:05:00'],
            ['kunjungan_id' => $tutup, 'produk_id' => 4, 'produk_kompetitor' => null, 'qty' => 0, 'created_at' => '2026-10-05 02:05:00'],
        ]);

        $this->get('/stok_outlet')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.name', 'lap_stok_outlet')
                ->where('tables.0.rows.total', 2)
                ->where('tables.0.rows.data.0.product_name', 'Adem Sari')
                ->where('tables.0.rows.data.0.unit_name', 'Botol')
                ->where('tables.0.rows.data.0.quantity', '4')
                ->where('tables.0.rows.data.0.category', 'Menjual Produk')
                ->where('tables.0.rows.data.0.datetime_checkin', '09:00:00')
                ->where('tables.0.rows.data.0.koordinator_name', 'Koor Satu')
                ->where('tables.0.rows.data.1.category', 'Toko Tutup')
            ));

        $this->get('/lap_stok_kompetitor')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.product_name', 'Larutan Kaki Tiga')
                ->where('tables.0.rows.data.0.quantity', '6')
                ->where('tables.0.rows.data.0.outlet_code', 'OUT-1')
            ));
    }

    public function test_barang_canvassing_pengembalian_dan_habis_terjual(): void
    {
        $c = DB::table('trx_canvassing')->insertGetId(['nomor' => 'CNV-1', 'karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-06',
            'status' => 'dikembalikan', 'diterima_at' => '2026-10-06 00:30:00', 'dikembalikan_at' => '2026-10-06 10:00:00']);
        $c2 = DB::table('trx_canvassing')->insertGetId(['nomor' => 'CNV-2', 'karyawan_id' => 3, 'depo_id' => 46, 'tanggal' => '2026-10-07',
            'status' => 'diterima', 'diterima_at' => '2026-10-07 00:30:00']);
        DB::table('trx_canvassing')->insert(['nomor' => 'CNV-3', 'karyawan_id' => 3, 'depo_id' => 46, 'tanggal' => '2026-10-08', 'status' => 'disiapkan']);
        DB::table('trx_canvassing_item')->insert([
            ['canvassing_id' => $c, 'produk_id' => 3, 'qty_bawa' => 10, 'qty_diterima' => 10, 'qty_kembali' => 0],
            ['canvassing_id' => $c, 'produk_id' => 4, 'qty_bawa' => 5, 'qty_diterima' => 5, 'qty_kembali' => 3],
            ['canvassing_id' => $c2, 'produk_id' => 3, 'qty_bawa' => 8, 'qty_diterima' => null, 'qty_kembali' => null],
        ]);
        $jual = DB::table('trx_penjualan')->insertGetId(['kode' => 'HP-1', 'kunjungan_id' => $this->kunjungan, 'karyawan_id' => 1, 'canvassing_id' => $c,
            'tanggal' => '2026-10-06', 'jumlah_sku' => 2, 'total_qty' => 12, 'total' => 1000, 'is_effective_call' => true]);
        DB::table('trx_penjualan_item')->insert([
            ['penjualan_id' => $jual, 'produk_id' => 3, 'qty' => 10, 'harga' => 100, 'total' => 1000],
            ['penjualan_id' => $jual, 'produk_id' => 4, 'qty' => 2, 'harga' => 100, 'total' => 200],
        ]);

        $this->get('/barang_canvassing')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.name', 'lap_barang_canvassing')
                ->where('tables.0.rows.total', 3) // CNV-3 belum diterima
                ->where('tables.0.rows.data.0.canvassing_no', 'CNV-2')
                ->where('tables.0.rows.data.0.total_qty', '8')
                ->where('tables.0.rows.data.0.returned', 'Belum')
                ->where('tables.0.rows.data.1.receive_datetime', '2026-10-06 07:30:00')
                ->where('tables.0.rows.data.1.sale_qty', '10')
                ->where('tables.0.rows.data.1.koordinator_name', 'Koor Satu')
            ));

        $this->get('/lap_pengembalian_barang')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 2)
                ->where('tables.0.rows.data.1.product_sku', 'SKU-4')
                ->where('tables.0.rows.data.1.return_qty', '3')
                ->where('tables.0.rows.data.1.return_datetime', '2026-10-06 17:00:00')
            ));

        $this->get('/lap_barang_habis_terjual?q=CNV-1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.data.0.status_terjual', 'Habis Terjual')
                ->where('tables.0.rows.data.0.pct_terjual', '100%')
                ->where('tables.0.rows.data.1.sisa_qty', '3')
                ->where('tables.0.rows.data.1.status_terjual', 'Belum Habis')
                ->where('tables.0.rows.data.1.pct_terjual', '40%')
            ));
    }
}
