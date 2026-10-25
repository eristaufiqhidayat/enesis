<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Riwayat penjualan, stok, canvassing & absensi koordinator aplikasi lama → tabel motoris (data rekaan).
class MigrasiPenjualanStokCanvassingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mobile.zona_waktu' => 'Asia/Jakarta']);

        DB::table('mk_depo')->insert([['id' => 46, 'nama' => 'Sidoarjo']]);
        DB::table('mk_karyawan')->insert([
            ['id' => 1, 'kategori' => 'sales', 'nama' => 'Andi', 'depo_id' => 46],
            ['id' => 9, 'kategori' => 'koordinator', 'nama' => 'Koor Sido', 'depo_id' => 46],
        ]);
        DB::table('mk_outlet')->insert([['id' => 11, 'kode' => 'OUT-1', 'nama' => 'Toko Satu'], ['id' => 12, 'kode' => 'OUT-2', 'nama' => 'Toko Dua']]);
        DB::table('mk_produk')->insert([['id' => 3, 'kode' => 'SKU-3', 'nama' => 'Adem Sari'], ['id' => 4, 'kode' => 'SKU-4', 'nama' => 'Plossa']]);

        // Kunjungan lama ke OUT-1 jam 09:00 sudah dimigrasi sebelumnya.
        DB::table('lap_checkin_outlet')->insert([
            'source_id' => 301, 'salesperson_name' => 'Andi', 'warehouse_name' => 'Sidoarjo', 'outlet_code' => 'OUT-1',
            'date_checkin' => '2026-10-01', 'datetime_checkin' => '2026-10-01 09:00:00', 'datetime_checkout' => '2026-10-01 09:10:00',
        ]);
        $this->artisan('enesis:migrasi-transaksi kunjungan')->assertSuccessful();
    }

    private function jual(int $src, string $kode, string $outlet, string $jam, string $ec, int $total): array
    {
        return [
            'source_id' => $src, 'trans_code' => $kode, 'salesperson_name' => 'Andi', 'warehouse_name' => 'Sidoarjo', 'outlet_code' => $outlet,
            'date_trans' => substr($jam, 0, 10), 'datetime_trans' => $jam, 'datetime_checkin' => $jam, 'datetime_checkout' => null,
            'effective_call_status' => $ec, 'total_item' => 2, 'total_qty' => 5, 'grand_total' => $total,
        ];
    }

    public function test_penjualan_lama_masuk_dengan_item_dan_kunjungan(): void
    {
        DB::table('lap_penjualan_barang')->insert([
            $this->jual(501, 'SLS-1', 'OUT-1', '2026-10-01 09:00:00', 'Ya', 45000),   // kunjungan sudah ada
            $this->jual(502, 'SLS-2', 'OUT-2', '2026-10-01 10:00:00', 'Tidak', 0),    // kunjungan dibuat
            $this->jual(503, 'SLS-3', 'OUT-X', '2026-10-01 11:00:00', 'Ya', 1000),    // outlet tidak dikenali
        ]);
        DB::table('lap_penjualan_barang_detail')->insert([
            ['source_id' => 1, 'trans_code' => 'SLS-1', 'product_sku' => 'SKU-3', 'quantity' => 3, 'price' => 9000, 'discount' => 0, 'total' => 27000, 'total_discount' => 0],
            ['source_id' => 2, 'trans_code' => 'SLS-1', 'product_sku' => 'SKU-4', 'quantity' => 2, 'price' => 9000, 'discount' => 0, 'total' => 18000, 'total_discount' => 0],
        ]);

        $this->artisan('enesis:migrasi-transaksi penjualan')->assertSuccessful();

        $this->assertSame(2, DB::table('trx_penjualan')->where('sumber', 'lama')->count());
        $p = DB::table('trx_penjualan')->where('sumber_id', 501)->first();
        $this->assertSame(['SLS-1', 1, 46, 11, '2026-10-01', 45000, true], [$p->kode, (int) $p->karyawan_id, (int) $p->depo_id, (int) $p->outlet_id, substr($p->tanggal, 0, 10), (int) $p->total, (bool) $p->is_effective_call]);
        $this->assertSame((int) DB::table('trx_kunjungan')->where('sumber_id', 301)->value('id'), (int) $p->kunjungan_id);
        $this->assertSame([3, 4], DB::table('trx_penjualan_item')->where('penjualan_id', $p->id)->orderBy('produk_id')->pluck('produk_id')->map(fn ($i) => (int) $i)->all());

        $p2 = DB::table('trx_penjualan')->where('sumber_id', 502)->first();
        $this->assertFalse((bool) $p2->is_effective_call);
        $k2 = DB::table('trx_kunjungan')->find($p2->kunjungan_id);
        $this->assertSame([12, 'lama', '2026-10-01 03:00:00'], [(int) $k2->outlet_id, $k2->sumber, substr($k2->checkin_at, 0, 19)]);

        // Diulang: tidak dobel, kunjungan tidak dibuat ulang.
        $this->artisan('enesis:migrasi-transaksi penjualan')->assertSuccessful();
        $this->assertSame(2, DB::table('trx_penjualan')->where('sumber', 'lama')->count());
        $this->assertSame(2, DB::table('trx_penjualan_item')->count());
        $this->assertSame(2, DB::table('trx_kunjungan')->count());
    }

    public function test_stok_outlet_dan_kompetitor_lama(): void
    {
        $stok = fn ($src, $lain) => $lain + ['source_id' => $src, 'quantity' => 4, 'salesperson_name' => 'Andi', 'outlet_code' => 'OUT-1',
            'date_checkin' => '2026-10-01', 'datetime_checkin' => '2026-10-01 09:00:00', 'last_update' => '2026-10-01 09:05:00'];
        DB::table('lap_stok_outlet')->insert([$stok(601, ['warehouse_name' => 'Sidoarjo', 'product_code' => 'SKU-3']), $stok(602, ['warehouse_name' => 'Sidoarjo', 'product_code' => 'TIDAK'])]);
        DB::table('lap_stok_kompetitor')->insert([$stok(601, ['product_name' => 'Larutan Kaki Tiga'])]); // source_id sama, tabel berbeda

        $this->artisan('enesis:migrasi-transaksi stok_outlet stok_kompetitor')->assertSuccessful();

        $kunjungan = (int) DB::table('trx_kunjungan')->where('sumber_id', 301)->value('id');
        $this->assertSame([[3, null, 4.0, 'lama'], [null, 'Larutan Kaki Tiga', 4.0, 'lama_komp']],
            DB::table('trx_stok_outlet')->where('kunjungan_id', $kunjungan)->orderBy('id')->get()
                ->map(fn ($s) => [$s->produk_id ? (int) $s->produk_id : null, $s->produk_kompetitor, (float) $s->qty, $s->sumber])->all());

        $this->artisan('enesis:migrasi-transaksi stok_outlet stok_kompetitor')->assertSuccessful();
        $this->assertSame(2, DB::table('trx_stok_outlet')->count());
    }

    public function test_canvassing_lama_dengan_pengembalian_dan_sambung_penjualan(): void
    {
        $item = fn ($src, $sku, $qty) => ['source_id' => $src, 'canvassing_no' => 'CNV-1', 'warehouse_name' => 'Sidoarjo', 'salesperson_name' => 'Andi',
            'receive_date' => '2026-10-01', 'receive_datetime' => '2026-10-01 07:00:00', 'product_sku' => $sku, 'total_qty' => $qty, 'sale_qty' => 0, 'return_qty' => 0, 'returned' => '1'];
        DB::table('lap_barang_canvassing')->insert([$item(701, 'SKU-3', 10), $item(702, 'SKU-4', 5)]);
        DB::table('lap_pengembalian_barang')->insert(['source_id' => 801, 'canvas_return_no' => 'RET-1', 'canvassing_no' => 'CNV-1', 'return_date' => '2026-10-01',
            'return_datetime' => '2026-10-01 17:00:00', 'warehouse_name' => 'Sidoarjo', 'salesperson_name' => 'Andi', 'product_sku' => 'SKU-3', 'total_qty' => 10, 'sale_qty' => 7, 'return_qty' => 3]);
        DB::table('lap_penjualan_barang')->insert($this->jual(501, 'SLS-1', 'OUT-1', '2026-10-01 09:00:00', 'Ya', 45000));

        $this->artisan('enesis:migrasi-transaksi penjualan canvassing')->assertSuccessful();

        $c = DB::table('trx_canvassing')->where('nomor', 'CNV-1')->first();
        $this->assertSame(['lama', 'dikembalikan', '2026-10-01 10:00:00', '2026-10-01 00:00:00'], [$c->sumber, $c->status, substr($c->dikembalikan_at, 0, 19), substr($c->diterima_at, 0, 19)]);
        $this->assertSame([[3, 10.0, 3.0], [4, 5.0, 0.0]], DB::table('trx_canvassing_item')->where('canvassing_id', $c->id)->orderBy('produk_id')->get()
            ->map(fn ($i) => [(int) $i->produk_id, (float) $i->qty_bawa, (float) $i->qty_kembali])->all());
        $this->assertSame($c->id, DB::table('trx_penjualan')->where('sumber_id', 501)->value('canvassing_id'));

        $this->artisan('enesis:migrasi-transaksi canvassing')->assertSuccessful();
        $this->assertSame(1, DB::table('trx_canvassing')->count());
        $this->assertSame(2, DB::table('trx_canvassing_item')->count());
    }

    public function test_absensi_koordinator_lama(): void
    {
        DB::table('lap_absensi_koordinator')->insert(['source_id' => 901, 'date' => '2026-10-01', 'time_in' => '2026-10-01 07:30:00',
            'time_out' => '2026-10-01 17:00:00', 'employee_name' => 'Koor Sido', 'warehouse_name' => 'Sidoarjo']);

        $this->artisan('enesis:migrasi-transaksi absensi_koordinator')->assertSuccessful();

        $a = DB::table('trx_absensi')->where('sumber_id', 901)->first();
        $this->assertSame([9, 46, '2026-10-01 00:30:00'], [(int) $a->karyawan_id, (int) $a->depo_id, substr($a->masuk_at, 0, 19)]);
    }
}
