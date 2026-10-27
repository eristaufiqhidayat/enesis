<?php

namespace Tests\Feature\Admin;

use App\Support\Area\AreaMk;
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
            ->where('areaUrl', route('peta-sebaran-sales.area'))
        );
    }

    public function test_posisi_terakhir_dan_status_kebaruan_sales_aktif(): void
    {
        DB::table('trx_tracking')->insert([
            ['karyawan_id' => 1, 'recorded_at' => '2026-10-07 03:00:00', 'lat' => -7.2, 'lng' => 112.2, 'baterai' => 75, 'mock_location' => true],
        ]);
        // Tegar: hanya riwayat aplikasi lama 3 hari lalu (jam WIB). Sinta: belum ada lokasi.
        DB::table('tracking_sales_lokasi')->insert(['sales_id' => 3, 'tanggal' => '2026-10-04', 'waktu' => '2026-10-04 15:00:00', 'lat' => -7.4, 'lng' => 112.4]);
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

    }

    private function area(array $kolom, array $cincin): int
    {
        $id = DB::table('area_polygon')->insertGetId($kolom + ['polygon' => json_encode($cincin)]);
        AreaMk::perbaruiKotak([$id]);

        return $id;
    }

    public function test_ringkasan_depo_dan_area_di_layar_dengan_nama_sales(): void
    {
        DB::table('area_depo')->insert(['warehouse_id' => 46, 'name' => 'Sidoarjo', 'lat' => -7.45, 'lng' => 112.7]);
        // Area Marketama (ID mk_area = source_id) dan area Enesis (1.000.000.000 + id).
        $waru = $this->area(['source_id' => 10, 'code' => 'AREA-A', 'name' => 'Waru-001', 'warehouse' => 'Sidoarjo', 'warehouse_id' => 46, 'village' => 'Waru'],
            [[112.72, -7.34], [112.74, -7.34], [112.74, -7.36]]);
        $baru = $this->area(['code' => 'AREA-B', 'name' => 'Gedangan', 'warehouse' => 'Sidoarjo', 'warehouse_id' => 46],
            [[112.70, -7.38], [112.71, -7.38], [112.71, -7.39]]);
        $jauh = $this->area(['source_id' => 20, 'code' => 'AREA-C', 'name' => 'Balaraja-001', 'warehouse' => 'Balaraja', 'warehouse_id' => 22],
            [[106.40, -6.20], [106.41, -6.20], [106.41, -6.21]]);
        DB::table('mk_karyawan_area')->insert([
            ['karyawan_id' => 1, 'area_id' => 10, 'aktif' => true],
            ['karyawan_id' => 3, 'area_id' => 10, 'aktif' => false],
            ['karyawan_id' => 4, 'area_id' => 10, 'aktif' => true], // Joko tidak aktif: tidak ditampilkan
            ['karyawan_id' => 2, 'area_id' => 1_000_000_000 + $baru, 'aktif' => false],
        ]);

        $data = $this->getJson('/peta_sebaran_sales/data')->assertOk();
        $depo = collect($data->json('depo'))->keyBy('id');
        $this->assertSame([2, 0, 3], [$depo[46]['jumlahArea'], $depo[46]['tanpaSales'], $depo[46]['jumlahSales']]);
        $this->assertSame([-7.45, 112.7], [$depo[46]['lat'], $depo[46]['lng']]);
        $this->assertSame(1, $depo[22]['tanpaSales']);
        $this->assertSame(1, collect($data->json('sales'))->firstWhere('nama', 'Rudi Hartono')['jumlahArea']);

        // Hanya area yang beririsan dengan layar (Sidoarjo), lengkap dengan polygon & sales.
        $layar = $this->getJson('/peta_sebaran_sales/area?bbox=112.6,-7.5,112.8,-7.3')->assertOk();
        $area = collect($layar->json('area'))->keyBy('kode');
        $this->assertEqualsCanonicalizing(['AREA-A', 'AREA-B'], $area->keys()->all());
        $this->assertSame([['id' => 1, 'nama' => 'Rudi Hartono', 'aktif' => true], ['id' => 3, 'nama' => 'Tegar Mahendra', 'aktif' => false]], $area['AREA-A']['sales']);
        $this->assertSame('Sinta Wulandari', $area['AREA-B']['sales'][0]['nama']);
        $this->assertSame([112.72, -7.34], $area['AREA-A']['geometri'][0][0][0]);
        $this->assertSame($waru, $area['AREA-A']['id']);

        // Semua area satu sales.
        $sinta = $this->getJson('/peta_sebaran_sales/area?karyawan_id=2')->assertOk();
        $this->assertSame(['AREA-B'], array_column($sinta->json('area'), 'kode'));

        $this->getJson('/peta_sebaran_sales/area?bbox=x')->assertUnprocessable();
        $this->assertNotNull(DB::table('area_polygon')->find($jauh)->min_lat);
    }

    public function test_area_baru_dari_design_area_langsung_punya_kotak_batas(): void
    {
        DB::table('area_depo')->insert(['warehouse_id' => 46, 'name' => 'Sidoarjo', 'province' => 'Jawa Timur']);
        $id = $this->postJson('/master_area/design_area/areas', [
            'name' => 'Baru', 'warehouse_id' => 46, 'province' => 'Jawa Timur', 'polygon' => [[112.1, -7.1], [112.2, -7.1], [112.2, -7.2]],
        ])->assertCreated()->json('id');

        $a = DB::table('area_polygon')->find($id);
        $this->assertEquals([112.1, -7.2, 112.2, -7.1], [(float) $a->min_lng, (float) $a->min_lat, (float) $a->max_lng, (float) $a->max_lat]);
    }

    public function test_koordinator_hanya_melihat_sales_bawahannya(): void
    {
        $koor = $this->akun('koor.satu', 'koordinator', 137);
        $this->actingAs($koor);

        $nama = collect($this->getJson('/peta_sebaran_sales/data')->assertOk()->json('sales'))->pluck('nama')->sort()->values()->all();

        $this->assertSame(['Rudi Hartono', 'Tegar Mahendra'], $nama);
    }
}
