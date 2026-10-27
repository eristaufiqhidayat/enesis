<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Akses\Akses;
use App\Support\Wilayah\BatasWilayah;
use App\Support\Wilayah\Geometri;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class GenerateKelurahanTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    private const URL_KEL = 'https://raw.githubusercontent.com/cahyadsn/wilayah_boundaries/main/db/kel/31/wilayah_boundaries_kel_31.71.sql';

    // Potongan berkas dataset: Gambir satu polygon, Kebon Kelapa dua pulau (kedalaman 4), koordinat [lat, lng].
    private const SQL = <<<'SQL'
DELETE FROM wilayah_boundaries WHERE kode LIKE '31.71.%' AND LENGTH(kode)=13;
INSERT INTO wilayah_boundaries(kode,nama,lat,lng,path) VALUES
	 ('31.71.01.1001','Gambir',-6.175,106.825,'[[[-6.17,106.82],[-6.17,106.83],[-6.18,106.83],[-6.18,106.82],[-6.17,106.82]]]'),
	 ('31.71.01.1005','Kebon Kelapa',-6.164,106.824,'[[[[-6.16,106.82],[-6.16,106.821],[-6.161,106.821],[-6.16,106.82]]],[[[-6.162,106.822],[-6.162,106.832],[-6.172,106.832],[-6.172,106.822],[-6.162,106.822]]]]'),
	 ('31.71.01.1006','D''Pulo',-6.163,106.804,'[[[-6.16,106.80],[-6.16,106.81],[-6.17,106.81],[-6.16,106.80]]]');
SQL;

    protected function setUp(): void
    {
        parent::setUp();

        $prov = DB::table('provinsi')->insertGetId(['kode' => 31, 'nama_provinsi' => 'DKI Jakarta']);
        $kota = DB::table('kota_kabupaten')->insertGetId(['provinsi' => 'DKI Jakarta', 'kode' => 3171, 'nama_kota_kabupaten' => 'Kota Jakarta Pusat']);
        DB::table('kecamatan')->insert(['provinsi' => 'DKI Jakarta', 'kota_kabupaten' => 'Kota Jakarta Pusat', 'kode' => '317101', 'nama_kecamatan' => 'Gambir']);
        foreach ([['3171011001', 'Gambir'], ['3171011005', 'Kebon Kelapa'], ['3171011004', 'Petojo Selatan'], [null, 'Setia Budi']] as [$kode, $nama]) {
            DB::table('kelurahan')->insert(['provinsi' => 'DKI Jakarta', 'kota_kabupaten' => 'Kota Jakarta Pusat', 'kecamatan' => 'Gambir', 'kode' => $kode, 'nama_kelurahan' => $nama]);
        }
        DB::table('area_depo')->insert(['warehouse_id' => 48, 'name' => 'Depo Plossa', 'province' => 'DKI Jakarta', 'lat' => -6.2115, 'lng' => 106.9155]);

        $this->prov = $prov;
        $this->kota = $kota;
        Http::preventStrayRequests();
    }

    private int $prov;

    private int $kota;

    private function kelurahanId(string $nama): int
    {
        return DB::table('kelurahan')->where('nama_kelurahan', $nama)->value('id');
    }

    public function test_page_renders_with_province_and_depot_props(): void
    {
        $this->get('/master_area/kelurahan_polygon')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Area/GenerateKelurahan')
                ->has('provinsi', 1, fn (Assert $p) => $p->where('nama', 'DKI Jakarta')->etc())
                ->has('depo', 1, fn (Assert $d) => $d->where('warehouse_id', 48)->etc())
                ->where('bisaTambah', true)
            );

        $this->getJson('/master_area/kelurahan_polygon/opsi?tingkat=kota&provinsi_id='.$this->prov)
            ->assertExactJson([['id' => $this->kota, 'kode' => 3171, 'nama' => 'Kota Jakarta Pusat']]);
        $this->getJson('/master_area/kelurahan_polygon/opsi?tingkat=kecamatan&kota_id='.$this->kota)
            ->assertJsonPath('0.nama', 'Gambir');
    }

    public function test_preview_downloads_city_boundaries_once_and_matches_by_code(): void
    {
        Http::fake([self::URL_KEL => Http::response(self::SQL)]);

        $res = $this->getJson('/master_area/kelurahan_polygon/pratinjau?kota_id='.$this->kota)->assertOk();

        $kel = collect($res->json('kelurahan'))->keyBy('nama');
        $this->assertCount(4, $kel);
        $this->assertNull($res->json('peringatan'));
        // Dua pulau Kebon Kelapa tetap utuh, koordinat sudah [lng, lat].
        $this->assertCount(2, $kel['Kebon Kelapa']['geometri']);
        $this->assertSame([106.82, -6.17], $kel['Gambir']['geometri'][0][0][0]);
        $this->assertEqualsWithDelta(1.23, $kel['Gambir']['luas_km2'], 0.02);
        // Tanpa batas resmi: kelurahan tanpa kode, dan kelurahan berkode yang tidak ada di dataset.
        $this->assertNull($kel['Setia Budi']['geometri']);
        $this->assertNull($kel['Petojo Selatan']['geometri']);
        $this->assertDatabaseHas('batas_wilayah', ['kode' => '3171011006', 'nama' => "D'Pulo"]);

        // Pratinjau kedua memakai tabel batas_wilayah, tidak mengunduh lagi.
        $this->getJson('/master_area/kelurahan_polygon/pratinjau?kota_id='.$this->kota)->assertOk();
        Http::assertSentCount(1);
    }

    public function test_preview_reports_download_failure_without_failing(): void
    {
        Http::fake([self::URL_KEL => Http::response('', 404)]);

        $this->getJson('/master_area/kelurahan_polygon/pratinjau?kota_id='.$this->kota)
            ->assertOk()
            ->assertJsonCount(4, 'kelurahan')
            ->assertJsonPath('peringatan', 'Batas wilayah belum tersedia di dataset (kel/31/wilayah_boundaries_kel_31.71.sql).');
    }

    public function test_save_creates_one_area_per_village_and_skips_duplicates_and_missing_boundaries(): void
    {
        Http::fake([self::URL_KEL => Http::response(self::SQL)]);
        $this->getJson('/master_area/kelurahan_polygon/pratinjau?kota_id='.$this->kota)->assertOk();

        $ids = [$this->kelurahanId('Gambir'), $this->kelurahanId('Kebon Kelapa'), $this->kelurahanId('Setia Budi')];
        $res = $this->postJson('/master_area/kelurahan_polygon/simpan', ['warehouse_id' => 48, 'kelurahan_ids' => $ids])
            ->assertCreated()
            ->assertJsonPath('dibuat', 2)
            ->assertJsonPath('dilewati.0.alasan', 'Tidak ada batas resmi');

        $prefix = 'AREA-'.now()->format('Ymd').'-';
        $this->assertSame([$prefix.'0001', $prefix.'0002'], $res->json('kode'));

        $area = DB::table('area_polygon')->where('kode_kelurahan', '3171011005')->first();
        $this->assertSame('Kebon Kelapa', $area->name);
        $this->assertSame('Depo Plossa', $area->warehouse);
        $this->assertSame($this->kelurahanId('Kebon Kelapa'), (int) $area->village_id);
        $this->assertSame($this->kota, (int) $area->city_id);
        $this->assertSame('Kel. Kebon Kelapa, Kec. Gambir', $area->info);
        // Kolom polygon lama = cincin pulau terluas (bukan yang titiknya terbanyak), geometri = semua pulau.
        $this->assertCount(4, json_decode($area->polygon));
        $this->assertSame([106.822, -6.162], json_decode($area->polygon)[0]);
        $this->assertCount(2, json_decode($area->geometri)->coordinates);

        // Simpan ulang: kelurahan yang sudah punya area dilewati, tidak dobel.
        $this->postJson('/master_area/kelurahan_polygon/simpan', ['warehouse_id' => 48, 'kelurahan_ids' => [$this->kelurahanId('Gambir')]])
            ->assertOk()
            ->assertJsonPath('dibuat', 0)
            ->assertJsonPath('dilewati.0.alasan', 'Sudah ada area '.$prefix.'0001 (Depo Plossa)');
        $this->assertSame(2, DB::table('area_polygon')->count());

        // Pratinjau menandai kelurahan yang sudah punya area.
        $this->getJson('/master_area/kelurahan_polygon/pratinjau?kota_id='.$this->kota)
            ->assertJsonPath('kelurahan.0.nama', 'Gambir')
            ->assertJsonPath('kelurahan.0.area.0.code', $prefix.'0001');
    }

    public function test_legacy_area_matched_by_name_counts_as_existing(): void
    {
        Http::fake([self::URL_KEL => Http::response(self::SQL)]);
        DB::table('area_polygon')->insert([
            'code' => 'AREA-20260101-0001', 'name' => 'Gambir Lama', 'warehouse' => 'Depo Lain', 'city' => 'Kota Jakarta Pusat',
            'district' => 'Gambir', 'village' => 'gambir', 'polygon' => json_encode([[106.8, -6.1], [106.9, -6.1], [106.9, -6.2]]),
        ]);

        $this->postJson('/master_area/kelurahan_polygon/simpan', ['warehouse_id' => 48, 'kelurahan_ids' => [$this->kelurahanId('Gambir')]])
            ->assertJsonPath('dibuat', 0)
            ->assertJsonPath('dilewati.0.alasan', 'Sudah ada area AREA-20260101-0001 (Depo Lain)');
    }

    public function test_save_requires_add_right_and_valid_depot(): void
    {
        $this->postJson('/master_area/kelurahan_polygon/simpan', ['warehouse_id' => 999, 'kelurahan_ids' => [1]])
            ->assertJsonValidationErrors('warehouse_id');

        Akses::lupakan();
        $role = DB::table('roles')->insertGetId(['kode' => 'uji', 'nama' => 'Uji', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_hak')->insert([
            'role_id' => $role, 'menu' => 'master_area',
            'lihat' => true, 'tambah' => false, 'ubah' => true, 'hapus' => false, 'ekspor' => false,
        ]);
        $this->actingAs(User::factory()->make(['id' => 2, 'role_id' => $role, 'aktif' => true, 'wajib_ganti_password' => false]));

        $this->get('/master_area/kelurahan_polygon')->assertInertia(fn (Assert $page) => $page->where('bisaTambah', false));
        $this->postJson('/master_area/kelurahan_polygon/simpan', ['warehouse_id' => 48, 'kelurahan_ids' => [1]])->assertForbidden();
    }

    public function test_largest_ring_is_chosen_by_area_and_command_imports_from_local_folder(): void
    {
        $multi = Geometri::dariPathLatLng(json_decode('[[[[0,0],[0,0.001],[0.0005,0.0005],[0.0001,0.0002],[0,0]]],[[[1,1],[1,2],[2,2],[2,1],[1,1]]]]', true));
        $this->assertSame([[1.0, 1.0], [2.0, 1.0], [2.0, 2.0], [1.0, 2.0]], Geometri::cincinTerbesar($multi));

        $dir = sys_get_temp_dir().'/batas-'.uniqid();
        mkdir($dir.'/kel/31', 0777, true);
        file_put_contents($dir.'/'.BatasWilayah::berkasKelurahan('3171'), self::SQL);

        $this->artisan('wilayah:impor-batas', ['--provinsi' => ['31'], '--dir' => $dir])->assertSuccessful();
        $this->assertSame(3, DB::table('batas_wilayah')->where('tingkat', 'kelurahan')->count());
        Http::assertNothingSent();
    }
}
