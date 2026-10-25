<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Akses\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class DesignAreaTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    private function seedArea(): void
    {
        DB::table('area_polygon')->insert([
            'code' => 'AREA-20261008-0001', 'name' => 'Banjarmasin', 'province' => 'Kalimantan Selatan',
            'city' => 'Kota Banjarmasin', 'district' => 'Banjarmasin Tengah', 'village' => 'Kertak Baru Ilir',
            'polygon' => json_encode([[114.588, -3.321], [114.589, -3.321], [114.589, -3.322]]),
        ]);
        DB::table('area_depo')->insert(['warehouse_id' => 48, 'name' => 'Banjarmasin', 'province' => 'Kalimantan Selatan', 'lat' => -3.36, 'lng' => 114.58]);
    }

    public function test_page_renders_as_inertia_component_with_region_and_warehouse_props(): void
    {
        $this->seedArea();

        $this->get('/master_area/design_area')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Area/Design')
                ->where('provinces', ['Kalimantan Selatan'])
                ->has('warehouses', 1, fn (Assert $depo) => $depo->where('warehouse_id', 48)->where('name', 'Banjarmasin')->etc())
                ->where('bisaUbah', true)
                ->where('bisaHapus', true)
            );

        $this->get('/loading_barang/add')->assertOk();
        $this->get('/barang_canvassing/add')->assertOk();
    }

    public function test_page_hides_save_and_delete_rights_when_role_lacks_them(): void
    {
        $this->seedArea();
        Akses::lupakan();

        $role = DB::table('roles')->insertGetId(['kode' => 'uji', 'nama' => 'Uji', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_hak')->insert([
            'role_id' => $role, 'menu' => 'master_area',
            'lihat' => true, 'tambah' => false, 'ubah' => false, 'hapus' => false, 'ekspor' => false,
        ]);
        $this->actingAs(User::factory()->make(['id' => 2, 'role_id' => $role, 'aktif' => true, 'wajib_ganti_password' => false]));

        $this->get('/master_area/design_area')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Area/Design')
                ->where('bisaUbah', false)
                ->where('bisaHapus', false)
            );
    }

    public function test_areas_and_cascading_options_filter_by_region(): void
    {
        $this->seedArea();

        $this->getJson('/master_area/design_area/options?level=city&province=Kalimantan%20Selatan')
            ->assertExactJson(['Kota Banjarmasin']);

        $this->getJson('/master_area/design_area/areas?province=Kalimantan%20Selatan')
            ->assertOk()
            ->assertJsonCount(1, 'areas')
            ->assertJsonPath('areas.0.polygon.0', [114.588, -3.321])
            ->assertJsonPath('warehouses.0.name', 'Banjarmasin');
    }

    public function test_new_polygon_is_saved_with_generated_code_and_can_be_deleted(): void
    {
        $this->seedArea();

        $response = $this->postJson('/master_area/design_area/areas', [
            'name' => 'Area Baru', 'warehouse_id' => 48, 'province' => 'Kalimantan Selatan',
            'polygon' => [[114.1, -3.1], [114.2, -3.1], [114.2, -3.2]],
        ])->assertCreated();

        $this->assertStringStartsWith('AREA-'.now()->format('Ymd').'-', $response->json('code'));
        $this->assertDatabaseHas('area_polygon', ['name' => 'Area Baru', 'warehouse' => 'Banjarmasin']);

        $this->deleteJson('/master_area/design_area/areas/'.$response->json('id'))->assertNoContent();
        $this->assertDatabaseMissing('area_polygon', ['name' => 'Area Baru']);
    }
}
