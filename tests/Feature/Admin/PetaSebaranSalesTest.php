<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

class PetaSebaranSalesTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 7)->setTime(12, 0));
    }

    public function test_halaman_dan_ikon_kanan_atas(): void
    {
        $this->get('/peta_sebaran_sales')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Tracking/Sebaran')
            ->where('dataUrl', route('peta-sebaran-sales.data'))
            ->where('lastSeenUrl', route('menu', 'tracking_sales'))
        );
    }

    public function test_posisi_terakhir_dan_status_kebaruan_sales_aktif(): void
    {
        DB::table('trx_tracking')->insert([
            ['karyawan_id' => 1, 'recorded_at' => '2026-10-07 03:00:00', 'lat' => -7.2, 'lng' => 112.2, 'baterai' => 75, 'mock_location' => true],
        ]);
        // Tegar: hanya riwayat aplikasi lama 3 hari lalu (jam WIB). Sinta: belum ada lokasi.
        DB::table('tracking_sales_lokasi')->insert(['sales_id' => 3, 'tanggal' => '2026-10-04', 'waktu' => '2026-10-04 15:00:00', 'lat' => -7.4, 'lng' => 112.4]);
        DB::table('area_depo')->insert(['warehouse_id' => 46, 'name' => 'Sidoarjo', 'lat' => -7.45, 'lng' => 112.7]);

        $res = $this->getJson('/peta_sebaran_sales/data')->assertOk();
        $sales = collect($res->json('sales'))->keyBy('nama');

        // Hanya sales aktif (Joko tidak aktif, koordinator & staf bukan sales).
        $this->assertEqualsCanonicalizing(['Rudi Hartono', 'Sinta Wulandari', 'Tegar Mahendra'], $sales->keys()->all());
        $this->assertSame('hari_ini', $sales['Rudi Hartono']['status']);
        $this->assertSame([-7.2, 112.2, 46, 'Sidoarjo', 'Koor Satu', true], [
            $sales['Rudi Hartono']['lat'], $sales['Rudi Hartono']['lng'], $sales['Rudi Hartono']['depoId'],
            $sales['Rudi Hartono']['depo'], $sales['Rudi Hartono']['koordinator'], $sales['Rudi Hartono']['lokasiPalsu'],
        ]);
        $this->assertSame('minggu', $sales['Tegar Mahendra']['status']);
        $this->assertSame('Aplikasi lama', $sales['Tegar Mahendra']['sumber']);
        $this->assertSame('belum', $sales['Sinta Wulandari']['status']);
        $this->assertNull($sales['Sinta Wulandari']['lat']);

        $this->assertSame([['id' => 46, 'nama' => 'Sidoarjo', 'lat' => -7.45, 'lng' => 112.7]], $res->json('depo'));
    }

    public function test_koordinator_hanya_melihat_sales_bawahannya(): void
    {
        $koor = $this->akun('koor.satu', 'koordinator', 137);
        $this->actingAs($koor);

        $nama = collect($this->getJson('/peta_sebaran_sales/data')->assertOk()->json('sales'))->pluck('nama')->sort()->values()->all();

        $this->assertSame(['Rudi Hartono', 'Tegar Mahendra'], $nama);
    }
}
