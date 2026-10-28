<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Support\Outlet\VerifikasiOutletBaru;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Data outlet baru selengkap Marketama (New Open Outlet): contact person & no HP kedua, wilayah dari titik GPS,
// tanggal daftar; disalin ke master outlet saat disetujui; menu New Open Outlet per tanggal daftar.
class OutletBaruLengkapTest extends MobileApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Batas kelurahan resmi (kotak di sekitar -6.25, 106.85) + master kelurahan berkode sama.
        $kotak = [[[[106.84, -6.26], [106.86, -6.26], [106.86, -6.24], [106.84, -6.24], [106.84, -6.26]]]];
        DB::table('batas_wilayah')->insert([
            'kode' => '3174061001', 'tingkat' => 'kelurahan', 'nama' => 'KAMPUNG MELAYU', 'lat' => -6.25, 'lng' => 106.85,
            'geojson' => json_encode(['type' => 'MultiPolygon', 'coordinates' => $kotak]), 'jumlah_titik' => 5, 'sumber' => 'uji',
        ]);
        DB::table('kelurahan')->insert([
            'kode' => '3174061001', 'nama_kelurahan' => 'Kampung Melayu', 'kecamatan' => 'Jatinegara',
            'kota_kabupaten' => 'Kota Jakarta Timur', 'provinsi' => 'DKI Jakarta',
        ]);
        // Area rute tanpa batas resmi di sekitarnya: wilayah dari data area.
        DB::table('mk_area')->where('id', 10)->update(['data' => json_encode([
            'province' => 'Jawa Barat', 'city' => 'Kota Bekasi', 'district' => 'Bekasi Barat', 'village' => 'Kranji',
        ])]);
    }

    private function web(string $method, string $uri, array $data = [])
    {
        $this->app['auth']->forgetGuards();
        $admin = User::where('username', 'admin')->first()
            ?? User::factory()->create(['username' => 'admin', 'kategori' => null, 'role_id' => null, 'aktif' => true, 'wajib_ganti_password' => false]);

        return $this->actingAs($admin, 'web')->call($method, $uri, $data);
    }

    public function test_daftar_outlet_menyimpan_kontak_kedua_wilayah_dan_tanggal(): void
    {
        $this->sales();
        $this->login();

        $id = $this->api('POST', 'outlets', [
            'name' => 'Warung Bu Sri', 'owner_name' => 'Sri', 'owner_name_2' => 'Joko', 'phone' => '081234567890', 'phone_2' => '0813 1111 2222',
            'type' => 'Warung', 'photo_store_id' => $this->foto('toko'), 'lat' => -6.25, 'lng' => 106.85, 'accuracy_m' => 10,
        ])->assertCreated()->json('data.new_outlet_id');

        $b = DB::table('trx_outlet_baru')->find($id);
        $this->assertSame(['Joko', '0813 1111 2222'], [$b->pemilik2, $b->no_hp2]);
        $this->assertSame(['DKI Jakarta', 'Kota Jakarta Timur', 'Jatinegara', 'Kampung Melayu', '3174061001'],
            [$b->provinsi, $b->kota_kabupaten, $b->kecamatan, $b->kelurahan, $b->kode_kelurahan]);
        $this->assertSame('2026-10-26', substr((string) $b->tanggal, 0, 10));

        // Di luar semua batas resmi: wilayah dari area rute (tanpa kode).
        $lain = $this->api('POST', 'outlets', [
            'name' => 'Toko Jauh', 'photo_store_id' => $this->foto('toko'), 'lat' => -6.5, 'lng' => 107.2, 'accuracy_m' => 10,
        ])->assertCreated()->json('data.new_outlet_id');
        $l = DB::table('trx_outlet_baru')->find($lain);
        $this->assertSame(['Jawa Barat', 'Kota Bekasi', 'Bekasi Barat', 'Kranji', null],
            [$l->provinsi, $l->kota_kabupaten, $l->kecamatan, $l->kelurahan, $l->kode_kelurahan]);
    }

    public function test_disetujui_menyalin_kontak_tipe_foto_dan_wilayah_ke_master(): void
    {
        $this->sales();
        $this->login();
        $foto = $this->foto('toko');
        $id = $this->api('POST', 'outlets', [
            'name' => 'Warung Bu Sri', 'owner_name' => 'Sri', 'owner_name_2' => 'Joko', 'phone' => '081234567890', 'phone_2' => '081311112222',
            'type' => 'Warung', 'photo_store_id' => $foto, 'lat' => -6.25, 'lng' => 106.85, 'accuracy_m' => 10,
        ])->assertCreated()->json('data.new_outlet_id');

        $this->web('POST', "/outlet_all/baru/{$id}/setujui")->assertSessionHas('sukses');

        $o = DB::table('mk_outlet')->find(VerifikasiOutletBaru::ID_AWAL);
        $this->assertSame(['Sri', 'Joko', '081234567890', '081311112222', 'Warung', $foto, 1],
            [$o->kontak1, $o->kontak2, $o->no_hp1, $o->no_hp2, $o->tipe, $o->foto_id, (int) $o->karyawan_id]);
        $this->assertSame(['DKI Jakarta', 'Kampung Melayu', '3174061001', 'Tidak'], [$o->provinsi, $o->kelurahan, $o->kode_kelurahan, $o->status_fiktif]);

        $this->api('GET', 'outlets/'.VerifikasiOutletBaru::ID_AWAL)->assertOk()
            ->assertJsonPath('data.owner_name_2', 'Joko')->assertJsonPath('data.phone_2', '081311112222')
            ->assertJsonPath('data.type', 'Warung')->assertJsonPath('data.village', 'Kampung Melayu');
    }

    public function test_menu_new_open_outlet_per_tanggal_daftar_dan_lap_semua_tanggal(): void
    {
        DB::table('trx_outlet_baru')->insert([
            ['karyawan_id' => 1, 'depo_id' => 46, 'area_id' => 10, 'nama' => 'Warung Hari Ini', 'lat' => -6.25, 'lng' => 106.85,
                'status' => 'menunggu', 'tanggal' => '2026-10-26', 'pemilik2' => 'Joko', 'no_hp2' => '0811', 'kelurahan' => 'Kampung Melayu'],
            ['karyawan_id' => 1, 'depo_id' => 46, 'area_id' => 10, 'nama' => 'Warung Kemarin', 'lat' => -6.25, 'lng' => 106.85,
                'status' => 'disetujui', 'tanggal' => '2026-10-25', 'pemilik2' => null, 'no_hp2' => null, 'kelurahan' => null],
        ]);

        // Bawaan hari ini (WIB), seperti Marketama.
        $this->web('GET', '/outlet_noo')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tanggal', '2026-10-26')
            ->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.outlet_name', 'Warung Hari Ini')
                ->where('tables.0.rows.data.0.contact_person2', 'Joko')
                ->where('tables.0.rows.data.0.phone2', '0811')
                ->where('tables.0.rows.data.0.village_name', 'Kampung Melayu')));

        $this->web('GET', '/outlet_noo?tanggal=2026-10-25')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $page) => $page->where('tables.0.rows.total', 1)->where('tables.0.rows.data.0.outlet_name', 'Warung Kemarin')));

        $this->web('GET', '/lap_outlet_noo')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $page) => $page->where('tables.0.rows.total', 2)));
    }

    public function test_perintah_mengisi_wilayah_data_lama(): void
    {
        DB::table('trx_outlet_baru')->insert(['id' => 7, 'karyawan_id' => 1, 'depo_id' => 46, 'area_id' => 10, 'nama' => 'Lama',
            'lat' => -6.255, 'lng' => 106.845, 'status' => 'menunggu']);
        DB::table('mk_outlet')->insert(['id' => VerifikasiOutletBaru::ID_AWAL + 5, 'nama' => 'Disetujui lama', 'area_id' => 10,
            'latitude' => -6.5, 'longitude' => 107.2]);

        $this->artisan('enesis:wilayah-outlet')->assertSuccessful();

        $this->assertSame('3174061001', DB::table('trx_outlet_baru')->where('id', 7)->value('kode_kelurahan'));
        $this->assertSame('Kranji', DB::table('mk_outlet')->where('id', VerifikasiOutletBaru::ID_AWAL + 5)->value('kelurahan'));
    }
}
