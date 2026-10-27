<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Wilayah\BatasWilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class AutoGridTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    private int $kota;

    private static function kotak(float $x1, float $y1, float $x2, float $y2): array
    {
        return [[[[$x1, $y1], [$x2, $y1], [$x2, $y2], [$x1, $y2], [$x1, $y1]]]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        DB::table('provinsi')->insert(['kode' => 31, 'nama_provinsi' => 'DKI Jakarta']);
        $this->kota = DB::table('kota_kabupaten')->insertGetId(['provinsi' => 'DKI Jakarta', 'kode' => 3171, 'nama_kota_kabupaten' => 'Kota Jakarta Pusat']);
        DB::table('kecamatan')->insert(['provinsi' => 'DKI Jakarta', 'kota_kabupaten' => 'Kota Jakarta Pusat', 'kode' => '317101', 'nama_kecamatan' => 'Gambir']);
        foreach ([['3171011001', 'Barat'], ['3171011002', 'Timur'], [null, 'Tanpa Kode']] as [$kode, $nama]) {
            DB::table('kelurahan')->insert(['provinsi' => 'DKI Jakarta', 'kota_kabupaten' => 'Kota Jakarta Pusat', 'kecamatan' => 'Gambir', 'kode' => $kode, 'nama_kelurahan' => $nama]);
        }
        // Dua kelurahan ±3,3 km × 3,3 km bersebelahan (total ±22,1 km²); batas sudah tersimpan, jadi tidak ada unduhan.
        app(BatasWilayah::class)->simpan([
            ['kode' => '3171011001', 'tingkat' => 'kelurahan', 'nama' => 'Barat', 'lat' => null, 'lng' => null, 'multi' => self::kotak(106.80, -6.20, 106.83, -6.17)],
            ['kode' => '3171011002', 'tingkat' => 'kelurahan', 'nama' => 'Timur', 'lat' => null, 'lng' => null, 'multi' => self::kotak(106.83, -6.20, 106.86, -6.17)],
        ]);
        DB::table('area_depo')->insert(['warehouse_id' => 48, 'name' => 'Depo Plossa', 'province' => 'DKI Jakarta', 'lat' => -6.2115, 'lng' => 106.9155]);
        DB::table('mk_wilayah_depo')->insert(['id' => 7, 'nama' => 'Jakarta Pusat', 'depo_id' => 48]);
    }

    private function pratinjau(array $q = []): TestResponse
    {
        return $this->getJson('/master_area/auto_design_area/pratinjau?'.http_build_query($q + ['kota_id' => $this->kota, 'ukuran' => 2, 'mode' => 'kelurahan']));
    }

    public function test_page_renders_with_sizes_and_depot_regions(): void
    {
        $this->get('/master_area/auto_design_area')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Area/AutoGrid')
                ->where('ukuran', range(1, 15))
                ->has('wilayahDepo', 1, fn (Assert $w) => $w->where('id', 7)->where('depo_id', 48)->etc())
                ->where('bisaTambah', true)
                ->where('bisaUbah', true)
                ->where('bisaHapus', true)
                ->where('designUrl', route('design-area.index'))
                ->where('hapusUrl', url('master_area/design_area/areas'))
            );

        $this->get('/master_area/design_area')
            ->assertInertia(fn (Assert $page) => $page->where('autoGridUrl', route('auto-grid.index')));
    }

    public function test_preview_builds_grid_from_official_boundaries_in_both_modes(): void
    {
        $perKel = $this->pratinjau()->assertOk();
        $this->assertSame('1 kelurahan tanpa batas resmi tidak ikut di grid (gambar manual di Design Area).', $perKel->json('peringatan'));
        $unit = collect($perKel->json('unit'));
        $this->assertTrue($unit->every(fn ($u) => count($u['kelurahan']) === 1 && $u['area'] === null));
        $this->assertEqualsWithDelta(22.13, $unit->sum('luas_km2'), 0.05);

        $kotak = collect($this->pratinjau(['mode' => 'kotak'])->assertOk()->json('unit'));
        $this->assertLessThan($unit->count(), $kotak->count());
        $this->assertTrue($kotak->contains(fn ($u) => count($u['kelurahan']) === 2));

        $this->pratinjau(['ukuran' => 20])->assertJsonValidationErrors('ukuran');
        $this->pratinjau(['mode' => 'acak'])->assertJsonValidationErrors('mode');
    }

    public function test_save_regenerates_on_server_and_skips_overlap_on_second_run(): void
    {
        $unit = collect($this->pratinjau(['mode' => 'kotak'])->json('unit'));
        $dua = $unit->first(fn ($u) => count($u['kelurahan']) === 2);
        $kunci = [$unit[0]['kunci'], $dua['kunci']];

        $res = $this->postJson('/master_area/auto_design_area/simpan', [
            'kota_id' => $this->kota, 'ukuran' => 2, 'mode' => 'kotak', 'warehouse_id' => 48, 'wilayah_depo_id' => 7, 'kunci' => $kunci,
        ])->assertCreated()->assertJsonPath('dibuat', 2);

        $prefix = 'AREA-'.now()->format('Ymd').'-';
        $this->assertSame([$prefix.'0001', $prefix.'0002'], $res->json('kode'));

        $area = DB::table('area_polygon')->where('name', $dua['nama'])->first();
        $this->assertSame('Depo Plossa', $area->warehouse);
        $this->assertSame('Jakarta Pusat', $area->branch_area);
        $this->assertSame(7, (int) $area->branch_area_id);
        $this->assertSame('grid-2km-kotak', $area->sumber_polygon);
        $this->assertSame($dua['kelurahan'][0]['id'], (int) $area->village_id);
        $this->assertSame($this->kota, (int) $area->city_id);
        $this->assertMatchesRegularExpression('/^Grid 2 km \S+ · Kel\. (Barat|Timur) \d+%, Kel\. (Barat|Timur) \d+%$/', $area->info);
        $this->assertSame($dua['geometri'], json_decode($area->geometri, true)['coordinates']);

        // Generate lagi: dua kotak tadi ditandai tumpang tindih dan dilewati saat disimpan ulang.
        $lagi = collect($this->pratinjau(['mode' => 'kotak'])->json('unit'))->keyBy('kunci');
        $this->assertSame($prefix.'0001', $lagi[$unit[0]['kunci']]['area']['code']);
        $this->postJson('/master_area/auto_design_area/simpan', [
            'kota_id' => $this->kota, 'ukuran' => 2, 'mode' => 'kotak', 'warehouse_id' => 48, 'kunci' => $kunci,
        ])->assertOk()->assertJsonPath('dibuat', 0)->assertJsonCount(2, 'dilewati');

        // Kunci yang tidak ada di grid (parameter berubah) ditolak.
        $this->postJson('/master_area/auto_design_area/simpan', [
            'kota_id' => $this->kota, 'ukuran' => 3, 'mode' => 'kotak', 'warehouse_id' => 48, 'kunci' => [$dua['kunci']],
        ])->assertJsonValidationErrors('kunci');
    }

    public function test_hexagon_mode_previews_and_saves(): void
    {
        $unit = collect($this->pratinjau(['mode' => 'segienam'])->assertOk()->json('unit'));
        $this->assertNotEmpty($unit);
        $this->assertTrue($unit->every(fn ($u) => str_starts_with($u['kunci'], 'h')));

        $this->postJson('/master_area/auto_design_area/simpan', [
            'kota_id' => $this->kota, 'ukuran' => 2, 'mode' => 'segienam', 'warehouse_id' => 48, 'kunci' => [$unit[0]['kunci']],
        ])->assertCreated()->assertJsonPath('dibuat', 1);

        $area = DB::table('area_polygon')->where('name', $unit[0]['nama'])->first();
        $this->assertSame('grid-2km-segienam', $area->sumber_polygon);
        $this->assertStringStartsWith('Segi enam 2 km '.$unit[0]['label'], $area->info);
    }

    public function test_old_marketama_area_does_not_mark_cells_as_overlapping(): void
    {
        $unit = collect($this->pratinjau(['mode' => 'kotak'])->json('unit'));
        // Segi enam Marketama besar yang menutupi seluruh wilayah uji.
        DB::table('area_polygon')->insert([
            'source_id' => 55, 'code' => 'AREA-20260713-0001', 'name' => 'Depo Lama-001', 'city' => 'Kota Jakarta Pusat',
            'polygon' => json_encode([[100, -10], [120, -10], [120, 0], [100, 0]]),
        ]);

        $lagi = collect($this->pratinjau(['mode' => 'kotak'])->json('unit'));
        $this->assertSame($unit->count(), $lagi->whereNull('area')->count());

        $this->postJson('/master_area/auto_design_area/simpan', [
            'kota_id' => $this->kota, 'ukuran' => 2, 'mode' => 'kotak', 'warehouse_id' => 48, 'kunci' => [$unit[0]['kunci']],
        ])->assertCreated()->assertJsonPath('dibuat', 1);
    }

    public function test_save_requires_add_right(): void
    {
        Akses::lupakan();
        $role = DB::table('roles')->insertGetId(['kode' => 'uji', 'nama' => 'Uji', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_hak')->insert(['role_id' => $role, 'menu' => 'master_area', 'lihat' => true, 'tambah' => false, 'ubah' => true, 'hapus' => false, 'ekspor' => false]);
        $this->actingAs(User::factory()->make(['id' => 2, 'role_id' => $role, 'aktif' => true, 'wajib_ganti_password' => false]));

        $this->get('/master_area/auto_design_area')->assertInertia(fn (Assert $page) => $page->where('bisaTambah', false));
        $this->postJson('/master_area/auto_design_area/simpan', ['kota_id' => $this->kota, 'ukuran' => 2, 'mode' => 'kotak', 'warehouse_id' => 48, 'kunci' => ['0.0']])
            ->assertForbidden();
    }
}
