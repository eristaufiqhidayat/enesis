<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Akses\Akses;
use Illuminate\Support\Facades\DB;

// Tanda outlet (potensi kecurangan dll.), melekat ke outlet (bukan per kunjungan/motoris): hak, validasi, cabut, audit.
class TandaOutletTest extends AdminTestCase
{
    private const URL = '/outlet_all/tanda';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('mk_outlet')->insert([
            ['id' => 10, 'kode' => 'OUT-10', 'nama' => 'Warung Aming'],
            ['id' => 20, 'kode' => 'OUT-20', 'nama' => 'Toko Dua'],
        ]);
    }

    /** Koordinator bawaan (hak Tambah pada outlet_all). */
    private function koordinator(string $username = 'koor'): User
    {
        return $this->akun($username, 'koordinator', 137);
    }

    private function akunDenganHak(string $username, array $aksi, string $cakupan = 'semua'): User
    {
        $role = $this->role('Peran '.$username, $cakupan, ['outlet_all' => $aksi]);

        return $this->akun($username, 'koordinator', 137, ['role_id' => $role]);
    }

    private function tandai(User $user, int $outlet = 10, array $lain = [])
    {
        return $this->actingAs($user)->postJson(self::URL, $lain + [
            'outlet_id' => $outlet, 'kategori' => 'outlet_fiktif', 'catatan' => 'Alamat tidak ditemukan',
        ]);
    }

    public function test_role_bawaan_koordinator_boleh_menandai_tapi_tidak_mencabut_tanda_orang_lain(): void
    {
        $koor = $this->koordinator();

        $this->assertTrue(Akses::bisa($koor, 'outlet_all', 'tambah'));
        $this->assertFalse(Akses::bisa($koor, 'outlet_all', 'hapus'));
    }

    public function test_menandai_menyimpan_tanda_dengan_pemberi_dan_riwayat_admin(): void
    {
        $koor = $this->koordinator();

        $this->tandai($koor)->assertCreated()
            ->assertJsonPath('kategori', 'outlet_fiktif')
            ->assertJsonPath('kategori_label', 'Outlet fiktif')
            ->assertJsonPath('catatan', 'Alamat tidak ditemukan')
            ->assertJsonPath('oleh', 'koor')
            ->assertJsonPath('bisa_cabut', true);

        $baris = DB::table('tanda_outlet')->first();
        $this->assertSame(10, (int) $baris->outlet_id);
        $this->assertSame($koor->id, (int) $baris->dibuat_oleh);
        $this->assertSame('koor', $baris->dibuat_nama);
        $this->assertNull($baris->dicabut_at);

        $catatan = DB::table('riwayat_admin')->where('objek', 'tanda_outlet')->first();
        $this->assertSame('tambah', $catatan->aksi);
        $this->assertSame((int) $baris->id, (int) $catatan->objek_id);
        $this->assertStringContainsString('Warung Aming', $catatan->keterangan);
        $this->assertStringContainsString('Outlet fiktif', $catatan->keterangan);
    }

    public function test_catatan_boleh_kosong(): void
    {
        $this->tandai($this->koordinator(), 10, ['catatan' => null])->assertCreated()->assertJsonPath('catatan', null);
    }

    public function test_daftar_hanya_memuat_tanda_aktif_pada_outlet_yang_diminta(): void
    {
        $koor = $this->koordinator();
        $aktif = $this->tandai($koor)->json('id');
        $dicabut = $this->tandai($koor, 10, ['kategori' => 'alamat_tidak_sesuai'])->json('id');
        $this->tandai($koor, 20);
        $this->actingAs($koor)->deleteJson(self::URL.'/'.$dicabut)->assertOk();

        $res = $this->actingAs($koor)->getJson(self::URL.'?outlet_id=10')->assertOk();

        $this->assertSame([$aktif], array_column($res->json('tanda'), 'id'));
        $res->assertJsonPath('bisa_tambah', true)
            ->assertJsonPath('kategori.alamat_tidak_sesuai', 'Alamat/lokasi tidak sesuai');
    }

    public function test_akun_hanya_lihat_bisa_melihat_tapi_tidak_menandai(): void
    {
        $this->tandai($this->koordinator());
        $pemantau = $this->akunDenganHak('pemantau', ['lihat']);

        $this->actingAs($pemantau)->getJson(self::URL.'?outlet_id=10')
            ->assertOk()->assertJsonPath('bisa_tambah', false)->assertJsonCount(1, 'tanda')->assertJsonPath('tanda.0.bisa_cabut', false);
        $this->tandai($pemantau)->assertForbidden();
        $this->assertSame(1, DB::table('tanda_outlet')->count());
    }

    public function test_akun_tanpa_hak_menu_ditolak(): void
    {
        $role = $this->role('Tanpa Outlet', 'semua', ['home' => ['lihat']]);
        $akun = $this->akun('tanpa', 'koordinator', 137, ['role_id' => $role]);

        $this->actingAs($akun)->getJson(self::URL.'?outlet_id=10')->assertForbidden();
        $this->tandai($akun)->assertForbidden();
    }

    public function test_outlet_yang_tidak_ada_di_master_ditolak(): void
    {
        $this->tandai($this->admin, 99999)->assertNotFound();
        $this->actingAs($this->admin)->getJson(self::URL.'?outlet_id=99999')->assertNotFound();
    }

    public function test_validasi_input(): void
    {
        $koor = $this->koordinator();

        $this->tandai($koor, 10, ['kategori' => 'tidak_dikenal'])->assertUnprocessable()->assertJsonValidationErrors('kategori');
        $this->tandai($koor, 10, ['kategori' => null])->assertUnprocessable()->assertJsonValidationErrors('kategori');
        $this->tandai($koor, 10, ['catatan' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors('catatan');
        $this->tandai($koor, 10, ['catatan' => str_repeat('a', 500)])->assertCreated();
        $this->actingAs($koor)->getJson(self::URL)->assertUnprocessable()->assertJsonValidationErrors('outlet_id');
    }

    public function test_pemberi_mencabut_tandanya_secara_soft_dan_tercatat(): void
    {
        $koor = $this->koordinator();
        $id = $this->tandai($koor)->json('id');

        $this->actingAs($koor)->deleteJson(self::URL.'/'.$id)->assertOk()->assertJsonPath('ok', true);

        $baris = DB::table('tanda_outlet')->find($id);
        $this->assertNotNull($baris, 'Baris tetap ada sebagai jejak.');
        $this->assertNotNull($baris->dicabut_at);
        $this->assertSame($koor->id, (int) $baris->dicabut_oleh);
        $this->assertSame(['tambah', 'hapus'], DB::table('riwayat_admin')->where('objek', 'tanda_outlet')->orderBy('id')->pluck('aksi')->all());
        $this->actingAs($koor)->getJson(self::URL.'?outlet_id=10')->assertJsonCount(0, 'tanda');
    }

    public function test_tanda_orang_lain_hanya_bisa_dicabut_dengan_hak_hapus(): void
    {
        $id = $this->tandai($this->koordinator())->json('id');
        $rekan = $this->akunDenganHak('rekan', ['lihat', 'tambah']);
        $penghapus = $this->akunDenganHak('penghapus', ['lihat', 'tambah', 'hapus']);

        $this->actingAs($rekan)->deleteJson(self::URL.'/'.$id)->assertForbidden();
        $this->assertNull(DB::table('tanda_outlet')->find($id)->dicabut_at);

        $this->actingAs($penghapus)->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->assertSame($penghapus->id, (int) DB::table('tanda_outlet')->find($id)->dicabut_oleh);
    }

    public function test_cabut_tanda_yang_sudah_dicabut_ditolak(): void
    {
        $koor = $this->koordinator();
        $id = $this->tandai($koor)->json('id');
        $this->actingAs($koor)->deleteJson(self::URL.'/'.$id)->assertOk();
        $this->actingAs($koor)->deleteJson(self::URL.'/'.$id)->assertNotFound();
    }

    public function test_hanya_pengguna_yang_login_yang_bisa_mengakses(): void
    {
        auth()->logout();

        $this->getJson(self::URL.'?outlet_id=10')->assertUnauthorized();
    }

    public function test_migration_memberi_hak_tambah_ke_role_bawaan_koordinator_saja(): void
    {
        DB::table('role_hak')->where('menu', 'outlet_all')->update(['tambah' => false]);
        $lain = $this->role('Pemantau Biasa', 'semua', ['outlet_all' => ['lihat']]);
        $tanpaMenu = $this->role('Tanpa Menu Outlet', 'semua', ['home' => ['lihat']]);

        (require database_path('migrations/2026_11_01_000005_beri_hak_tanda_outlet_koordinator.php'))->up();

        $this->assertTrue((bool) DB::table('role_hak')->where('role_id', $this->roleId('koordinator'))->where('menu', 'outlet_all')->value('tambah'));
        $this->assertFalse((bool) DB::table('role_hak')->where('role_id', $lain)->where('menu', 'outlet_all')->value('tambah'));
        $this->assertFalse(DB::table('role_hak')->where('role_id', $tanpaMenu)->where('menu', 'outlet_all')->exists());
        $this->assertFalse((bool) DB::table('role_hak')->where('role_id', $this->roleId('lainnya'))->where('menu', 'outlet_all')->value('tambah'));
    }
}
