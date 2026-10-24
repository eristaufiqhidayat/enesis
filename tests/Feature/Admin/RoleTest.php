<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;

// Role & hak akses: daftar, tambah/edit matriks hak, anggota + pindah, hapus, riwayat.
class RoleTest extends AdminTestCase
{
    public function test_role_bawaan_dari_migrasi_dan_daftar_role(): void
    {
        $this->akun('rudi.h', 'sales', 1);
        DB::table('users')->insert(['name' => 'Baru', 'username' => 'baru.sinkron', 'password' => 'x', 'kategori' => 'sales', 'aktif' => true, 'role_id' => null]);

        $this->assertSame(['koordinator', 'lainnya', 'sales', 'super_admin'], DB::table('roles')->orderBy('kode')->pluck('kode')->all());
        $this->assertTrue((bool) DB::table('roles')->where('kode', 'sales')->value('login_mobile'));
        $this->assertFalse((bool) DB::table('roles')->where('kode', 'sales')->value('login_web'));

        $this->get('/role')->assertOk()
            ->assertSee('Super Admin')->assertSee('Sales Motoris')->assertSee('Per downline')->assertSee('BAWAAN')
            ->assertSeeInOrder(['Sales Motoris', 'Diri sendiri', '1 menu', '2']); // akun tanpa role_id ikut role kategorinya
        $this->get('/home')->assertSee('"slug":"role"', false); // menu sidebar (props Inertia)
    }

    public function test_tambah_role_dengan_matriks_hak(): void
    {
        $this->get('/role/tambah')->assertOk()->assertSee('Hak akses per menu')->assertSee('name="hak[user_sales][ubah]"', false);

        $this->post('/role', [
            'nama' => 'Admin Depo',
            'keterangan' => 'Muatan & pengembalian',
            'cakupan' => 'depo',
            'login_web' => '1',
            'hak' => [
                'home' => ['lihat' => '1'],
                'barang_canvassing' => ['lihat' => '1', 'tambah' => '1', 'ubah' => '1'],
                'user_sales' => ['tambah' => '1'], // tanpa lihat: tidak berlaku
                'menu_tidak_ada' => ['lihat' => '1'],
            ],
        ])->assertRedirect('/role');

        $role = DB::table('roles')->where('nama', 'Admin Depo')->first();
        $this->assertSame('admin_depo', $role->kode);
        $this->assertSame('depo', $role->cakupan);
        $this->assertFalse((bool) $role->login_mobile);
        $this->assertEqualsCanonicalizing(['home', 'barang_canvassing'], DB::table('role_hak')->where('role_id', $role->id)->pluck('menu')->all());
        $this->assertTrue((bool) DB::table('role_hak')->where('role_id', $role->id)->where('menu', 'barang_canvassing')->value('tambah'));

        $this->post('/role', ['nama' => 'admin depo', 'cakupan' => 'semua'])->assertSessionHasErrors();
        $this->post('/role', ['nama' => 'Admin Depo', 'cakupan' => 'semua'])->assertSessionHasErrors('nama');
    }

    public function test_ubah_role_langsung_berlaku_dan_tercatat(): void
    {
        $roleId = $this->role('Supervisor', 'semua', ['home' => ['lihat']]);
        $sup = $this->akun('sup', 'lainnya', 500, ['role_id' => $roleId]);

        $this->actingAs($sup)->get('/provinsi')->assertForbidden();

        $this->actingAs($this->admin)->put("/role/{$roleId}", [
            'nama' => 'Supervisor', 'cakupan' => 'depo', 'login_web' => '1',
            'hak' => ['home' => ['lihat' => '1'], 'provinsi' => ['lihat' => '1', 'ekspor' => '1']],
        ])->assertRedirect("/role/{$roleId}/edit");

        $this->actingAs($sup)->get('/provinsi')->assertOk();

        $riwayat = DB::table('riwayat_admin')->where('objek', 'role')->where('objek_id', $roleId)->pluck('keterangan')->all();
        $this->assertContains('Cakupan data: Semua → Depo sendiri', $riwayat);
        $this->assertContains('Hak ditambah: Lihat Provinsi, Ekspor Provinsi', $riwayat);

        $this->actingAs($this->admin)->get("/role/{$roleId}/anggota")->assertOk()
            ->assertSee('sup')->assertSee('Cakupan data: Semua → Depo sendiri');
    }

    public function test_tidak_bisa_mengunci_diri_sendiri_dan_super_admin_selalu_penuh(): void
    {
        $roleId = $this->role('Pengelola', 'semua', ['home' => ['lihat'], 'role' => ['lihat', 'ubah']]);
        $pengelola = $this->akun('pengelola', 'lainnya', 500, ['role_id' => $roleId]);

        $this->actingAs($pengelola)->put("/role/{$roleId}", [
            'nama' => 'Pengelola', 'cakupan' => 'semua', 'login_web' => '1', 'hak' => ['home' => ['lihat' => '1'], 'role' => ['lihat' => '1']],
        ])->assertSessionHasErrors('hak');

        // Matriks Super Admin diabaikan; login web tidak bisa ditutup.
        $super = $this->roleId('super_admin');
        $this->actingAs($this->admin)->get("/role/{$super}/edit")->assertOk()->assertSee('Super Admin selalu punya semua hak');
        $this->put("/role/{$super}", ['nama' => 'Super Admin', 'cakupan' => 'semua', 'hak' => []])->assertRedirect();
        $this->assertTrue((bool) DB::table('roles')->where('id', $super)->value('login_web'));
        $this->get('/provinsi')->assertOk();
    }

    public function test_pindah_anggota_dan_hapus_role(): void
    {
        $tujuan = $this->role('Admin Depo', 'depo', ['home' => ['lihat']]);
        $kosong = $this->role('Kosong', 'diri', []);
        $a = $this->akun('rudi.h', 'sales', 1);
        $b = $this->akun('sinta', 'sales', 2);
        $sales = $this->roleId('sales');

        $this->get("/role/{$sales}/anggota")->assertOk()->assertSee('rudi.h')->assertSee('sinta');
        $this->post("/role/{$sales}/pindah", ['user_id' => [$a->id], 'ke' => $tujuan])->assertRedirect("/role/{$sales}/anggota");
        $this->assertSame($tujuan, (int) $a->fresh()->role_id);
        $this->assertSame($sales, (int) $b->fresh()->role_id);
        $this->post("/role/{$sales}/pindah", ['ke' => $tujuan])->assertSessionHasErrors('user_id');

        $this->delete("/role/{$sales}")->assertSessionHasErrors('hapus'); // bawaan
        $this->delete("/role/{$tujuan}")->assertSessionHasErrors('hapus'); // masih ada anggota
        $this->delete("/role/{$kosong}")->assertRedirect('/role');
        $this->assertDatabaseMissing('roles', ['id' => $kosong]);
    }

    public function test_tanpa_hak_role_tidak_bisa_membuka_role(): void
    {
        $this->actingAs($this->akun('koor', 'koordinator', 137));

        $this->get('/role')->assertForbidden();
        $this->post('/role', ['nama' => 'X', 'cakupan' => 'semua'])->assertForbidden();
        $this->get('/home')->assertOk(); // koordinator bawaan tetap bisa melihat menu laporan
    }
}
