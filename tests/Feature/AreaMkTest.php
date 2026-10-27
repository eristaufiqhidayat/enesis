<?php

namespace Tests\Feature;

use App\Support\Area\AreaMk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class AreaMkTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    private const POLYGON = [[106.80, -6.10], [106.81, -6.10], [106.81, -6.11]];

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('area_depo')->insert(['warehouse_id' => 48, 'name' => 'Banjarmasin', 'province' => 'DKI Jakarta', 'lat' => -6.10, 'lng' => 106.80]);
    }

    private function buat(): array
    {
        return $this->postJson('/master_area/design_area/areas', [
            'name' => 'Area Baru', 'warehouse_id' => 48, 'province' => 'DKI Jakarta', 'city' => 'Kota Jakarta Pusat', 'polygon' => self::POLYGON,
        ])->assertCreated()->json();
    }

    public function test_new_and_edited_area_is_copied_to_mk_area_with_offset_id(): void
    {
        $baru = $this->buat();
        $idMk = AreaMk::ID_AWAL + $baru['id'];

        $mk = DB::table('mk_area')->find($idMk);
        $this->assertSame([$baru['code'], 'Area Baru', 48], [$mk->kode, $mk->nama, (int) $mk->depo_id]);
        $this->assertSame('enesis', json_decode($mk->data, true)['sumber']);
        $this->assertNotNull(DB::table('area_polygon')->find($baru['id'])->diubah_pada);

        $this->putJson('/master_area/design_area/areas/'.$baru['id'], ['name' => 'Area Ganti', 'warehouse_id' => 48])->assertOk();
        $this->assertSame('Area Ganti', DB::table('mk_area')->find($idMk)->nama);
    }

    public function test_marketama_area_keeps_original_id(): void
    {
        $id = DB::table('area_polygon')->insertGetId(['source_id' => 777, 'code' => 'AREA-20260713-0001', 'name' => 'Lama', 'polygon' => json_encode(self::POLYGON)]);
        DB::table('mk_area')->insert(['id' => 777, 'kode' => 'AREA-20260713-0001', 'nama' => 'Lama']);

        $this->putJson('/master_area/design_area/areas/'.$id, ['name' => 'Lama Diubah'])->assertOk();

        $this->assertSame('Lama Diubah', DB::table('mk_area')->find(777)->nama);
        $this->assertSame(1, DB::table('mk_area')->count());
    }

    public function test_area_in_use_cannot_be_deleted_and_free_area_is_removed_from_mk_area(): void
    {
        $baru = $this->buat();
        $idMk = AreaMk::ID_AWAL + $baru['id'];
        DB::table('mk_karyawan_area')->insert(['karyawan_id' => 5, 'area_id' => $idMk, 'aktif' => true]);

        $this->deleteJson('/master_area/design_area/areas/'.$baru['id'])
            ->assertUnprocessable()
            ->assertJsonPath('rujukan.0', '1 penugasan sales');
        $this->assertNotNull(DB::table('area_polygon')->find($baru['id']));

        DB::table('mk_karyawan_area')->delete();
        $this->deleteJson('/master_area/design_area/areas/'.$baru['id'])->assertNoContent();
        $this->assertNull(DB::table('mk_area')->find($idMk));
    }

    public function test_master_area_menu_reads_area_polygon_with_shop_count_sales_and_distance(): void
    {
        $baru = $this->buat();
        $idMk = AreaMk::ID_AWAL + $baru['id'];
        DB::table('mk_outlet')->insert([['id' => 1, 'nama' => 'Toko A', 'area_id' => $idMk], ['id' => 2, 'nama' => 'Toko B', 'area_id' => $idMk]]);
        DB::table('mk_karyawan')->insert(['id' => 5, 'kategori' => 'sales', 'nama' => 'Budi']);
        DB::table('mk_karyawan_area')->insert(['karyawan_id' => 5, 'area_id' => $idMk, 'aktif' => true]);

        $this->get('/master_area')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('subtitle', 'Data diambil langsung dari tabel aplikasi motoris.')
            ->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.kode', $baru['code'])
                ->where('tables.0.rows.data.0.total_toko', 2)
                ->where('tables.0.rows.data.0.nama_sales', 'Budi')
                ->where('tables.0.rows.data.0.depo', 'Banjarmasin')
                ->where('tables.0.rows.data.0.jarak_terdekat_ke_depo', '0,00 km')
                ->missing('tables.0.rows.data.0._polygon')
            ));
    }

    public function test_command_backfills_enesis_areas(): void
    {
        $id = DB::table('area_polygon')->insertGetId(['code' => 'AREA-20261027-0001', 'name' => 'Grid', 'polygon' => json_encode(self::POLYGON)]);

        $this->artisan('area:sinkron-mk')->assertSuccessful();

        $this->assertSame('Grid', DB::table('mk_area')->find(AreaMk::ID_AWAL + $id)->nama);
    }
}
