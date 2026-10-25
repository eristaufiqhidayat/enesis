<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Dasar uji kelola akun & role: master karyawan rekaan dan akun admin (role bawaan Super Admin).
abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('mk_depo')->insert([['id' => 46, 'nama' => 'Sidoarjo'], ['id' => 22, 'nama' => 'Balaraja']]);
        DB::table('mk_karyawan')->insert([
            ['id' => 137, 'kategori' => 'koordinator', 'nama' => 'Koor Satu', 'depo_id' => 46, 'koordinator_id' => null, 'aktif' => true],
            ['id' => 1, 'kategori' => 'sales', 'nama' => 'Rudi Hartono', 'depo_id' => 46, 'koordinator_id' => 137, 'aktif' => true],
            ['id' => 2, 'kategori' => 'sales', 'nama' => 'Sinta Wulandari', 'depo_id' => 22, 'koordinator_id' => null, 'aktif' => true],
            ['id' => 3, 'kategori' => 'sales', 'nama' => 'Tegar Mahendra', 'depo_id' => 46, 'koordinator_id' => 137, 'aktif' => true],
            ['id' => 4, 'kategori' => 'sales', 'nama' => 'Joko Lama', 'depo_id' => 46, 'koordinator_id' => 137, 'aktif' => false],
            ['id' => 500, 'kategori' => 'lainnya', 'nama' => 'Staf Gudang', 'depo_id' => 46, 'koordinator_id' => null, 'aktif' => true],
        ]);

        $this->admin = $this->akun('admin', 'super_admin', null);
        $this->actingAs($this->admin);
    }

    protected function akun(string $username, string $kategori, ?int $karyawan, array $lain = []): User
    {
        return User::factory()->create($lain + [
            'name' => ucfirst($username),
            'username' => $username,
            'password' => 'rahasia123',
            'kategori' => $kategori,
            'karyawan_id' => $karyawan,
            'role_id' => DB::table('roles')->where('kode', $kategori)->value('id'),
        ]);
    }

    protected function roleId(string $kode): int
    {
        return (int) DB::table('roles')->where('kode', $kode)->value('id');
    }

    /** Pastikan respons adalah halaman Inertia $komponen, lalu kembalikan props-nya. */
    protected function inertiaProps(TestResponse $res, string $komponen): array
    {
        $res->assertInertia(fn (Assert $page) => $page->component($komponen));

        return $res->viewData('page')['props'];
    }

    /** Buat role baru dengan hak tertentu: ['menu' => ['lihat', 'ubah'], ...]. */
    protected function role(string $nama, string $cakupan, array $hak, bool $web = true, bool $mobile = false): int
    {
        $id = DB::table('roles')->insertGetId([
            'kode' => (string) str($nama)->slug('_'), 'nama' => $nama, 'bawaan' => false, 'cakupan' => $cakupan,
            'login_web' => $web, 'login_mobile' => $mobile, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($hak as $menu => $aksi) {
            DB::table('role_hak')->insert(['role_id' => $id, 'menu' => $menu] + array_merge(array_fill_keys(['lihat', 'tambah', 'ubah', 'hapus', 'ekspor'], false), array_fill_keys($aksi, true)));
        }

        return $id;
    }
}
