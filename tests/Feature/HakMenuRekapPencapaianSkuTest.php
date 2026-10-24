<?php

namespace Tests\Feature;

use App\Support\Akses\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Hak role atas dua menu lama dipindah & digabung ke menu rekap_pencapaian_sku (migrasi data).
class HakMenuRekapPencapaianSkuTest extends TestCase
{
    use RefreshDatabase;

    private const AKSI = ['lihat', 'tambah', 'ubah', 'hapus', 'ekspor'];

    private function jalankanMigrasi(): void
    {
        (require database_path('migrations/2026_10_24_000010_pindah_hak_menu_rekap_pencapaian_sku.php'))->up();
    }

    private function hak(int $roleId, string $menu, array $aksi): void
    {
        DB::table('role_hak')->insert(['role_id' => $roleId, 'menu' => $menu] + array_replace(array_fill_keys(self::AKSI, false), array_fill_keys($aksi, true)));
    }

    public function test_hak_menu_lama_digabung_ke_menu_baru_per_role(): void
    {
        $role = DB::table('roles')->insertGetId(['kode' => 'uji', 'nama' => 'Uji', 'created_at' => now(), 'updated_at' => now()]);
        $this->hak($role, 'rekap_pencapaian_sku_motoris', ['lihat']);
        $this->hak($role, 'rekap_pencapaian_depo_sku', ['lihat', 'ekspor']);

        $this->jalankanMigrasi();

        $this->assertSame(
            ['lihat' => true, 'tambah' => false, 'ubah' => false, 'hapus' => false, 'ekspor' => true],
            array_map(fn ($v) => (bool) $v, (array) DB::table('role_hak')->where('role_id', $role)->where('menu', 'rekap_pencapaian_sku')->first(self::AKSI))
        );
        $this->assertSame(0, DB::table('role_hak')->whereIn('menu', ['rekap_pencapaian_sku_motoris', 'rekap_pencapaian_depo_sku'])->count());
    }

    public function test_hak_menu_baru_yang_sudah_ada_tidak_berkurang(): void
    {
        $role = DB::table('roles')->insertGetId(['kode' => 'uji', 'nama' => 'Uji', 'created_at' => now(), 'updated_at' => now()]);
        $this->hak($role, 'rekap_pencapaian_sku', ['ubah']);
        $this->hak($role, 'rekap_pencapaian_sku_motoris', ['lihat']);

        $this->jalankanMigrasi();

        $this->assertTrue(Akses::hakRole($role)['rekap_pencapaian_sku']['ubah']);
        $this->assertTrue(Akses::hakRole($role)['rekap_pencapaian_sku']['lihat']);
    }

    public function test_role_tanpa_hak_menu_lama_tidak_diubah(): void
    {
        $role = DB::table('roles')->insertGetId(['kode' => 'uji', 'nama' => 'Uji', 'created_at' => now(), 'updated_at' => now()]);
        $this->hak($role, 'provinsi', ['lihat']);

        $this->jalankanMigrasi();

        $this->assertSame(1, DB::table('role_hak')->where('role_id', $role)->where('menu', 'provinsi')->count());
        $this->assertSame(0, DB::table('role_hak')->where('role_id', $role)->where('menu', 'rekap_pencapaian_sku')->count());
    }
}
