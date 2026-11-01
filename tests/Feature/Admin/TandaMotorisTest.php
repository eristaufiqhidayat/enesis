<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Akses\Akses;
use Illuminate\Support\Facades\DB;

// Tanda motoris (potensi kecurangan dll.) dari drawer Riwayat Lacak Motoris: hak, cakupan role, validasi, cabut, audit.
class TandaMotorisTest extends AdminTestCase
{
    private const URL = '/tracking_sales/tanda';

    private string $kemarin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kemarin = now()->subDay()->toDateString();
    }

    /** Koordinator bawaan (hak Tambah pada tracking_sales), downline: Rudi (1) dan Tegar (3); Sinta (2) bukan. */
    private function koordinator(string $username = 'koor'): User
    {
        return $this->akun($username, 'koordinator', 137);
    }

    private function akunDenganHak(string $username, array $aksi, string $cakupan = 'semua'): User
    {
        $role = $this->role('Peran '.$username, $cakupan, ['tracking_sales' => $aksi]);

        return $this->akun($username, 'koordinator', 137, ['role_id' => $role]);
    }

    private function tandai(User $user, int $sales = 1, array $lain = [])
    {
        return $this->actingAs($user)->postJson(self::URL, $lain + [
            'sales_id' => $sales, 'tanggal' => $this->kemarin, 'kategori' => 'penjualan_fiktif', 'catatan' => 'Nota tidak wajar',
        ]);
    }

    public function test_tanda_kembar_pada_motoris_tanggal_dan_kategori_yang_sama_ditolak(): void
    {
        $koor = $this->koordinator();
        $this->tandai($koor)->assertCreated();

        $res = $this->tandai($koor)->assertUnprocessable()->assertJsonValidationErrors('kategori');
        $this->assertStringContainsString('sudah ditandai oleh koor', $res->json('errors.kategori.0'));
        $this->assertSame(1, DB::table('tanda_motoris')->where('karyawan_id', 1)->count());

        // Kategori lain, tanggal lain, atau motoris lain tetap boleh.
        $this->tandai($koor, 1, ['kategori' => 'lokasi_palsu'])->assertCreated();
        $this->tandai($koor, 1, ['tanggal' => now()->subDays(2)->toDateString()])->assertCreated();
        $this->tandai($koor, 3)->assertCreated();
    }

    public function test_role_bawaan_koordinator_boleh_menandai_tapi_tidak_mencabut_tanda_orang_lain(): void
    {
        $koor = $this->koordinator();

        $this->assertTrue(Akses::bisa($koor, 'tracking_sales', 'tambah'));
        $this->assertFalse(Akses::bisa($koor, 'tracking_sales', 'hapus'));
    }

    public function test_menandai_menyimpan_tanda_dengan_pemberi_dan_riwayat_admin(): void
    {
        $koor = $this->koordinator();

        $this->tandai($koor)->assertCreated()
            ->assertJsonPath('kategori', 'penjualan_fiktif')
            ->assertJsonPath('kategori_label', 'Penjualan fiktif')
            ->assertJsonPath('catatan', 'Nota tidak wajar')
            ->assertJsonPath('oleh', 'koor')
            ->assertJsonPath('bisa_cabut', true);

        $baris = DB::table('tanda_motoris')->first();
        $this->assertSame(1, (int) $baris->karyawan_id);
        $this->assertSame($this->kemarin, substr((string) $baris->tanggal, 0, 10));
        $this->assertSame($koor->id, (int) $baris->dibuat_oleh);
        $this->assertSame('koor', $baris->dibuat_nama);
        $this->assertNull($baris->dicabut_at);

        $catatan = DB::table('riwayat_admin')->where('objek', 'tanda_motoris')->first();
        $this->assertSame('tambah', $catatan->aksi);
        $this->assertSame((int) $baris->id, (int) $catatan->objek_id);
        $this->assertStringContainsString('Rudi Hartono', $catatan->keterangan);
        $this->assertStringContainsString('Penjualan fiktif', $catatan->keterangan);
    }

    public function test_catatan_boleh_kosong(): void
    {
        $this->tandai($this->koordinator(), 1, ['catatan' => null])->assertCreated()->assertJsonPath('catatan', null);
    }

    public function test_daftar_hanya_memuat_tanda_aktif_pada_motoris_dan_tanggal_yang_diminta(): void
    {
        $koor = $this->koordinator();
        $aktif = $this->tandai($koor)->json('id');
        $dicabut = $this->tandai($koor, 1, ['kategori' => 'lokasi_palsu'])->json('id');
        $this->tandai($koor, 1, ['tanggal' => now()->subDays(3)->toDateString()]);
        $this->tandai($koor, 3);
        $this->actingAs($koor)->deleteJson(self::URL.'/'.$dicabut)->assertOk();

        $res = $this->actingAs($koor)->getJson(self::URL.'?sales_id=1&tanggal='.$this->kemarin)->assertOk();

        $this->assertSame([$aktif], array_column($res->json('tanda'), 'id'));
        $res->assertJsonPath('bisa_tambah', true)
            ->assertJsonPath('kategori.lokasi_palsu', 'Lokasi / GPS palsu');
    }

    public function test_akun_hanya_lihat_bisa_melihat_tapi_tidak_menandai(): void
    {
        $this->tandai($this->koordinator());
        $pemantau = $this->akunDenganHak('pemantau', ['lihat']);

        $this->actingAs($pemantau)->getJson(self::URL.'?sales_id=1&tanggal='.$this->kemarin)
            ->assertOk()->assertJsonPath('bisa_tambah', false)->assertJsonCount(1, 'tanda')->assertJsonPath('tanda.0.bisa_cabut', false);
        $this->tandai($pemantau)->assertForbidden();
        $this->assertSame(1, DB::table('tanda_motoris')->count());
    }

    public function test_akun_tanpa_hak_menu_ditolak(): void
    {
        $role = $this->role('Tanpa Tracking', 'semua', ['home' => ['lihat']]);
        $akun = $this->akun('tanpa', 'koordinator', 137, ['role_id' => $role]);

        $this->actingAs($akun)->getJson(self::URL.'?sales_id=1&tanggal='.$this->kemarin)->assertForbidden();
        $this->tandai($akun)->assertForbidden();
    }

    public function test_koordinator_tidak_bisa_menandai_atau_melihat_motoris_di_luar_cakupannya(): void
    {
        $koor = $this->koordinator();

        // Sinta (2) tidak punya koordinator 137.
        $this->tandai($koor, 2)->assertNotFound();
        $this->actingAs($koor)->getJson(self::URL.'?sales_id=2&tanggal='.$this->kemarin)->assertNotFound();
        $this->assertSame(0, DB::table('tanda_motoris')->count());
    }

    public function test_hanya_karyawan_kategori_sales_yang_bisa_ditandai(): void
    {
        $this->tandai($this->admin, 137)->assertNotFound(); // koordinator
        $this->tandai($this->admin, 500)->assertNotFound(); // staf lainnya
        $this->tandai($this->admin, 99999)->assertNotFound();
    }

    public function test_validasi_input(): void
    {
        $koor = $this->koordinator();

        $this->tandai($koor, 1, ['kategori' => 'tidak_dikenal'])->assertUnprocessable()->assertJsonValidationErrors('kategori');
        $this->tandai($koor, 1, ['kategori' => null])->assertUnprocessable()->assertJsonValidationErrors('kategori');
        $this->tandai($koor, 1, ['tanggal' => now()->addDays(2)->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('tanggal');
        $this->tandai($koor, 1, ['tanggal' => 'bukan-tanggal'])->assertUnprocessable()->assertJsonValidationErrors('tanggal');
        $this->tandai($koor, 1, ['catatan' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors('catatan');
        $this->tandai($koor, 1, ['catatan' => str_repeat('a', 500)])->assertCreated();
        $this->actingAs($koor)->getJson(self::URL.'?tanggal='.$this->kemarin)->assertUnprocessable()->assertJsonValidationErrors('sales_id');
    }

    public function test_pemberi_mencabut_tandanya_secara_soft_dan_tercatat(): void
    {
        $koor = $this->koordinator();
        $id = $this->tandai($koor)->json('id');

        $this->actingAs($koor)->deleteJson(self::URL.'/'.$id)->assertOk()->assertJsonPath('ok', true);

        $baris = DB::table('tanda_motoris')->find($id);
        $this->assertNotNull($baris, 'Baris tetap ada sebagai jejak.');
        $this->assertNotNull($baris->dicabut_at);
        $this->assertSame($koor->id, (int) $baris->dicabut_oleh);
        $this->assertSame(['tambah', 'hapus'], DB::table('riwayat_admin')->where('objek', 'tanda_motoris')->orderBy('id')->pluck('aksi')->all());
        $this->actingAs($koor)->getJson(self::URL.'?sales_id=1&tanggal='.$this->kemarin)->assertJsonCount(0, 'tanda');
    }

    public function test_tanda_outlet_yang_didaftarkan_motoris_ikut_dalam_daftar_drawer(): void
    {
        $koor = $this->koordinator();
        $this->tandai($koor);

        // Outlet didaftarkan Rudi (nama di mk_outlet), outlet lain lewat HP (trx_outlet_baru), outlet milik orang lain, dan tanda dicabut.
        DB::table('mk_outlet')->insert([
            ['id' => 9001, 'nama' => 'Toko Rudi', 'dibuat_oleh' => 'Rudi Hartono', 'dibuat_pada' => '02 Mar 2026 09:23'],
            ['id' => 9002, 'nama' => 'Toko HP', 'dibuat_oleh' => 'Orang Lain', 'dibuat_pada' => null],
            ['id' => 9003, 'nama' => 'Toko Sinta', 'dibuat_oleh' => 'Sinta Wulandari', 'dibuat_pada' => null],
            ['id' => 9004, 'nama' => 'Toko Rudi Dicabut', 'dibuat_oleh' => 'Rudi Hartono', 'dibuat_pada' => null],
        ]);
        DB::table('trx_outlet_baru')->insert(['karyawan_id' => 1, 'outlet_id' => 9002, 'nama' => 'Toko HP', 'lat' => -7.4, 'lng' => 112.7, 'status' => 'disetujui', 'recorded_at' => '2026-10-01 01:15:00']);
        $tanda = fn (int $outlet, array $lain = []) => DB::table('tanda_outlet')->insert($lain + [
            'outlet_id' => $outlet, 'kategori' => array_key_first(config('marketama.tanda_outlet')), 'catatan' => 'Alamat tidak ada',
            'dibuat_oleh' => $this->admin->id, 'dibuat_nama' => 'admin', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tanda(9001);
        $tanda(9002);
        $tanda(9003);
        $tanda(9004, ['dicabut_at' => now()]);

        $res = $this->actingAs($koor)->getJson(self::URL.'?sales_id=1&tanggal='.$this->kemarin)->assertOk();
        $this->assertEqualsCanonicalizing(['Toko Rudi', 'Toko HP'], array_column($res->json('outlet'), 'outlet'));
        $res->assertJsonPath('outlet.0.oleh', 'admin');
        // Waktu daftar NOO: outlet lewat HP memakai recorded_at (UTC -> lokal), outlet Marketama memakai dibuat_pada apa adanya.
        $didaftarkan = collect($res->json('outlet'))->pluck('didaftarkan', 'outlet');
        $this->assertSame('02 Mar 2026 09:23', $didaftarkan['Toko Rudi']);
        $this->assertNotNull($didaftarkan['Toko HP']);
    }

    public function test_outlet_terdekat_menampilkan_outlet_bertanda_di_sekitar_terurut_jarak(): void
    {
        DB::table('mk_outlet')->insert([
            ['id' => 9101, 'nama' => 'Acuan', 'dibuat_oleh' => 'x', 'latitude' => -7.4000, 'longitude' => 112.7000],
            ['id' => 9102, 'nama' => 'Dekat', 'dibuat_oleh' => 'x', 'latitude' => -7.4020, 'longitude' => 112.7000],
            ['id' => 9103, 'nama' => 'Agak Jauh', 'dibuat_oleh' => 'x', 'latitude' => -7.4100, 'longitude' => 112.7000],
            ['id' => 9104, 'nama' => 'Terlalu Jauh', 'dibuat_oleh' => 'x', 'latitude' => -7.5000, 'longitude' => 112.7000],
            ['id' => 9105, 'nama' => 'Tanpa Tanda', 'dibuat_oleh' => 'x', 'latitude' => -7.4010, 'longitude' => 112.7000],
            ['id' => 9106, 'nama' => 'Tanda Dicabut', 'dibuat_oleh' => 'x', 'latitude' => -7.4015, 'longitude' => 112.7000],
        ]);
        $kategori = array_key_first(config('marketama.tanda_outlet'));
        $tanda = fn (int $outlet, array $lain = []) => DB::table('tanda_outlet')->insert($lain + [
            'outlet_id' => $outlet, 'kategori' => $kategori, 'dibuat_oleh' => $this->admin->id, 'dibuat_nama' => 'admin', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $tanda(9101); // acuan sendiri tidak ikut
        $tanda(9103);
        $tanda(9102);
        $tanda(9102);
        $tanda(9104);
        $tanda(9106, ['dicabut_at' => now()]);

        $koor = $this->koordinator();
        $res = $this->actingAs($koor)->getJson(self::URL.'/outlet-terdekat?outlet_id=9101&radius_m=2000')->assertOk();

        $this->assertSame(['Dekat', 'Agak Jauh'], array_column($res->json('sekitar'), 'outlet'));
        $res->assertJsonPath('sekitar.0.jumlah_tanda', 2)->assertJsonPath('radius_m', 2000);
        $this->assertGreaterThan(150, $res->json('sekitar.0.jarak_m'));
        $this->assertLessThan(300, $res->json('sekitar.0.jarak_m'));

        // Radius lebih kecil menyaring yang lebih jauh; tanpa radius_m dipakai bawaan config (ambang jarak dekat).
        $this->actingAs($koor)->getJson(self::URL.'/outlet-terdekat?outlet_id=9101&radius_m=500')
            ->assertOk()->assertJsonCount(1, 'sekitar')->assertJsonPath('sekitar.0.outlet', 'Dekat')->assertJsonPath('radius_m', 500);
        $this->actingAs($koor)->getJson(self::URL.'/outlet-terdekat?outlet_id=9101')
            ->assertOk()->assertJsonCount(0, 'sekitar')->assertJsonPath('radius_m', config('marketama.deteksi_jarak_kunjungan.jarak_dekat_meter'));
        $this->actingAs($koor)->getJson(self::URL.'/outlet-terdekat?outlet_id=9101&radius_m=0')->assertUnprocessable();
        $this->actingAs($koor)->getJson(self::URL.'/outlet-terdekat?outlet_id=9101&radius_m=10001')->assertUnprocessable();
    }

    public function test_outlet_terdekat_kosong_bila_acuan_tanpa_koordinat_dan_404_bila_tidak_ada(): void
    {
        DB::table('mk_outlet')->insert(['id' => 9201, 'nama' => 'Tanpa Koordinat', 'dibuat_oleh' => 'x']);
        $koor = $this->koordinator();

        $this->actingAs($koor)->getJson(self::URL.'/outlet-terdekat?outlet_id=9201')->assertOk()->assertJsonCount(0, 'sekitar');
        $this->actingAs($koor)->getJson(self::URL.'/outlet-terdekat?outlet_id=99999')->assertNotFound();
        $this->actingAs($koor)->getJson(self::URL.'/outlet-terdekat')->assertUnprocessable();
    }

    public function test_pemberi_mengubah_catatan_dan_tercatat(): void
    {
        $koor = $this->koordinator();
        $id = $this->tandai($koor)->json('id');

        $this->actingAs($koor)->patchJson(self::URL.'/'.$id, ['catatan' => 'Catatan baru'])
            ->assertOk()->assertJsonPath('catatan', 'Catatan baru')->assertJsonPath('kategori', 'penjualan_fiktif');

        $this->assertSame('Catatan baru', DB::table('tanda_motoris')->find($id)->catatan);
        $this->assertSame(['tambah', 'ubah'], DB::table('riwayat_admin')->where('objek', 'tanda_motoris')->orderBy('id')->pluck('aksi')->all());
    }

    public function test_catatan_boleh_dikosongkan_saat_ubah(): void
    {
        $koor = $this->koordinator();
        $id = $this->tandai($koor)->json('id');

        $this->actingAs($koor)->patchJson(self::URL.'/'.$id, ['catatan' => null])->assertOk()->assertJsonPath('catatan', null);
        $this->assertNull(DB::table('tanda_motoris')->find($id)->catatan);
    }

    public function test_validasi_catatan_saat_ubah(): void
    {
        $koor = $this->koordinator();
        $id = $this->tandai($koor)->json('id');

        $this->actingAs($koor)->patchJson(self::URL.'/'.$id, ['catatan' => str_repeat('a', 501)])
            ->assertUnprocessable()->assertJsonValidationErrors('catatan');
    }

    public function test_catatan_orang_lain_hanya_bisa_diubah_dengan_hak_hapus(): void
    {
        $id = $this->tandai($this->koordinator())->json('id');
        $rekan = $this->akunDenganHak('rekan', ['lihat', 'tambah']);
        $penghapus = $this->akunDenganHak('penghapus', ['lihat', 'tambah', 'hapus']);

        $this->actingAs($rekan)->patchJson(self::URL.'/'.$id, ['catatan' => 'Dicoba rekan'])->assertForbidden();
        $this->assertSame('Nota tidak wajar', DB::table('tanda_motoris')->find($id)->catatan);

        $this->actingAs($penghapus)->patchJson(self::URL.'/'.$id, ['catatan' => 'Diubah penghapus'])->assertOk();
        $this->assertSame('Diubah penghapus', DB::table('tanda_motoris')->find($id)->catatan);
    }

    public function test_ubah_tanda_yang_sudah_dicabut_atau_di_luar_cakupan_ditolak(): void
    {
        $koor = $this->koordinator();
        $id = $this->tandai($koor)->json('id');
        $this->actingAs($koor)->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->actingAs($koor)->patchJson(self::URL.'/'.$id, ['catatan' => 'Tetap dicoba'])->assertNotFound();

        $luar = $this->tandai($this->admin, 2)->assertCreated()->json('id');
        $this->actingAs($koor)->patchJson(self::URL.'/'.$luar, ['catatan' => 'Tetap dicoba'])->assertNotFound();
    }

    public function test_tanda_tanggal_lain_memuat_catatan_dan_bisa_cabut_untuk_review_langsung(): void
    {
        $koor = $this->koordinator();
        $idHariLain = $this->tandai($koor, 1, ['tanggal' => now()->subDays(2)->toDateString(), 'catatan' => 'Catatan hari lain'])->json('id');

        $res = $this->actingAs($koor)->getJson(self::URL.'?sales_id=1&tanggal='.$this->kemarin)->assertOk();

        $res->assertJsonCount(1, 'lainnya')
            ->assertJsonPath('lainnya.0.id', $idHariLain)
            ->assertJsonPath('lainnya.0.catatan', 'Catatan hari lain')
            ->assertJsonPath('lainnya.0.bisa_cabut', true);
    }

    public function test_tanda_orang_lain_hanya_bisa_dicabut_dengan_hak_hapus(): void
    {
        $id = $this->tandai($this->koordinator())->json('id');
        $rekan = $this->akunDenganHak('rekan', ['lihat', 'tambah']);
        $penghapus = $this->akunDenganHak('penghapus', ['lihat', 'tambah', 'hapus']);

        $this->actingAs($rekan)->deleteJson(self::URL.'/'.$id)->assertForbidden();
        $this->assertNull(DB::table('tanda_motoris')->find($id)->dicabut_at);

        $this->actingAs($penghapus)->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->assertSame($penghapus->id, (int) DB::table('tanda_motoris')->find($id)->dicabut_oleh);
    }

    public function test_cabut_tanda_yang_sudah_dicabut_atau_di_luar_cakupan_ditolak(): void
    {
        $koor = $this->koordinator();
        $id = $this->tandai($koor)->json('id');
        $this->actingAs($koor)->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->actingAs($koor)->deleteJson(self::URL.'/'.$id)->assertNotFound();

        // Tanda untuk Sinta (di luar downline koor) dibuat admin; koor tidak bisa mencabutnya.
        $luar = $this->tandai($this->admin, 2)->assertCreated()->json('id');
        $this->actingAs($koor)->deleteJson(self::URL.'/'.$luar)->assertNotFound();
        $this->assertNull(DB::table('tanda_motoris')->find($luar)->dicabut_at);
    }

    public function test_hanya_pengguna_yang_login_yang_bisa_mengakses(): void
    {
        auth()->logout();

        $this->getJson(self::URL.'?sales_id=1&tanggal='.$this->kemarin)->assertUnauthorized();
    }

    public function test_migration_memberi_hak_tambah_ke_role_bawaan_koordinator_saja(): void
    {
        DB::table('role_hak')->where('menu', 'tracking_sales')->update(['tambah' => false]);
        $lain = $this->role('Pemantau Biasa', 'semua', ['tracking_sales' => ['lihat']]);
        $tanpaMenu = $this->role('Tanpa Menu Tracking', 'semua', ['home' => ['lihat']]);

        (require database_path('migrations/2026_11_01_000002_beri_hak_tanda_motoris_koordinator.php'))->up();

        $this->assertTrue((bool) DB::table('role_hak')->where('role_id', $this->roleId('koordinator'))->where('menu', 'tracking_sales')->value('tambah'));
        $this->assertFalse((bool) DB::table('role_hak')->where('role_id', $lain)->where('menu', 'tracking_sales')->value('tambah'));
        $this->assertFalse(DB::table('role_hak')->where('role_id', $tanpaMenu)->where('menu', 'tracking_sales')->exists());
        // Role bawaan lain (lainnya) tidak ikut berubah.
        $this->assertFalse((bool) DB::table('role_hak')->where('role_id', $this->roleId('lainnya'))->where('menu', 'tracking_sales')->value('tambah'));
    }
}
