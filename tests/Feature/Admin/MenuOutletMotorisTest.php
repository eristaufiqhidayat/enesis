<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Menu outlet NOO / AC / EC / OSS / RO / All dari master outlet + aktivitas motoris.
class MenuOutletMotorisTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('mk_area')->insert([['id' => 70, 'nama' => 'Sidoarjo-01', 'depo_id' => 46], ['id' => 80, 'nama' => 'Balaraja-01', 'depo_id' => 22]]);
        $o = fn ($id, $kode, $area) => ['id' => $id, 'kode' => $kode, 'nama' => "Toko {$id}", 'area_id' => $area];
        DB::table('mk_outlet')->insert([$o(1, 'OUT-1', 70), $o(2, 'OUT-2', 70), $o(3, 'OUT-3', 70), $o(4, 'OUT-4', 70), $o(5, 'OUT-5', 80)]);

        $kunjungan = fn ($outlet, $tgl) => DB::table('trx_kunjungan')->insertGetId(['karyawan_id' => 1, 'outlet_id' => $outlet, 'tanggal' => $tgl, 'checkin_at' => "{$tgl} 02:00:00"]);
        $ec = fn ($kode, $outlet, $tgl, $kj, $isEc = true) => DB::table('trx_penjualan')->insert(['kode' => $kode, 'kunjungan_id' => $kj, 'karyawan_id' => 1,
            'outlet_id' => $outlet, 'tanggal' => $tgl, 'jumlah_sku' => 1, 'total_qty' => 1, 'total' => 1000, 'is_effective_call' => $isEc]);

        // OUT-1: EC di dua tanggal (EC + RO). OUT-2: EC sekali. OUT-3: dikunjungi, kunjungan terakhir tanpa stok (AC + OSS).
        // OUT-4: dikunjungi, kunjungan terakhir ada stok (AC saja). OUT-5: tidak pernah dikunjungi.
        $ec('A', 1, '2026-10-01', $kunjungan(1, '2026-10-01'));
        $ec('B', 1, '2026-10-02', $kunjungan(1, '2026-10-02'));
        $ec('C', 2, '2026-10-01', $kunjungan(2, '2026-10-01'));
        $ec('D', 3, '2026-10-01', $kunjungan(3, '2026-10-01'), false);
        $k4 = $kunjungan(4, '2026-10-01');
        DB::table('trx_stok_outlet')->insert(['kunjungan_id' => $k4, 'produk_id' => 9, 'qty' => 5]);
        $kunjungan(3, '2026-10-02');
    }

    private function kode(string $url): array
    {
        $hasil = [];
        $this->get($url)->assertOk()->assertInertia(function (Assert $p) use (&$hasil) {
            $p->loadDeferredProps(function (Assert $p) use (&$hasil) {
                $hasil = collect($p->toArray()['props']['tables'][0]['rows']['data'])->pluck('outlet_code')->sort()->values()->all();
            });
        });

        return $hasil;
    }

    public function test_status_outlet(): void
    {
        $this->assertSame(['OUT-1', 'OUT-2'], $this->kode('/outlet_ec'));
        $this->assertSame(['OUT-3', 'OUT-4'], $this->kode('/outlet_ac'));
        $this->assertSame(['OUT-3'], $this->kode('/lap_outlet_oss'));
        $this->assertSame(['OUT-1', 'OUT-2', 'OUT-3', 'OUT-4', 'OUT-5'], $this->kode('/lap_outlet_all'));

        $ro = [];
        $this->get('/outlet_ro')->assertOk()->assertInertia(function (Assert $p) use (&$ro) {
            $p->loadDeferredProps(function (Assert $p) use (&$ro) {
                $ro = $p->toArray()['props']['tables'][0]['rows']['data'];
            });
        });
        $this->assertSame(['OUT-1'], array_column($ro, 'kode_outlet'));
        $this->assertSame('Sidoarjo', $ro[0]['depo']);
    }

    public function test_noo_dari_outlet_baru_hp(): void
    {
        DB::table('trx_outlet_baru')->insert([
            ['id' => 1, 'karyawan_id' => 1, 'depo_id' => 46, 'area_id' => 70, 'nama' => 'Warung Baru', 'pemilik' => 'Bu Siti', 'no_hp' => '0812',
                'lat' => -7.4, 'lng' => 112.7, 'status' => 'disetujui', 'outlet_id' => 2, 'recorded_at' => '2026-10-06 02:00:00'],
            ['id' => 2, 'karyawan_id' => 1, 'depo_id' => 46, 'area_id' => 70, 'nama' => 'Warung Menunggu', 'pemilik' => null, 'no_hp' => null,
                'lat' => -7.4, 'lng' => 112.7, 'status' => 'menunggu', 'outlet_id' => null, 'recorded_at' => '2026-10-07 02:00:00'],
        ]);

        $this->get('/outlet_noo')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 2)
                ->where('tables.0.rows.data.0.outlet_name', 'Warung Menunggu')
                ->where('tables.0.rows.data.0.status', 'Menunggu verifikasi')
                ->where('tables.0.rows.data.1.outlet_code', 'OUT-2')
                ->where('tables.0.rows.data.1.contact_person1', 'Bu Siti')
                ->where('tables.0.rows.data.1.created_at', '2026-10-06 09:00:00')
                ->where('tables.0.rows.data.1.koordinator_name', 'Koor Satu')
            ));
    }

    public function test_cakupan_depo(): void
    {
        $staf = $this->akun('staf.balaraja', 'lainnya', 2, ['role_id' => $this->role('Admin Depo', 'depo', ['lap_outlet_all' => ['lihat']])]);
        $this->actingAs($staf);

        $this->assertSame(['OUT-5'], $this->kode('/lap_outlet_all'));
    }
}
