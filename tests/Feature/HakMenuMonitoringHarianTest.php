<?php

namespace Tests\Feature;

use App\Support\Akses\Akses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Hak role atas tiga menu lama dipindah & digabung ke menu monitoring_harian_sales (migrasi data).
class HakMenuMonitoringHarianTest extends TestCase
{
    use RefreshDatabase;

    private const AKSI = ['lihat', 'tambah', 'ubah', 'hapus', 'ekspor'];

    private function jalankanMigrasi(): void
    {
        (require database_path('migrations/2026_10_24_000009_pindah_hak_menu_monitoring_harian.php'))->up();
    }

    private function hak(int $roleId, string $menu, array $aksi): void
    {
        DB::table('role_hak')->insert(['role_id' => $roleId, 'menu' => $menu] + array_replace(array_fill_keys(self::AKSI, false), array_fill_keys($aksi, true)));
    }

    public function test_hak_menu_lama_digabung_ke_menu_baru_per_role(): void
    {
        $role = DB::table('roles')->insertGetId(['kode' => 'uji', 'nama' => 'Uji', 'created_at' => now(), 'updated_at' => now()]);
        $this->hak($role, 'checkin_pagi', ['lihat']);
        $this->hak($role, 'sales_tidak_absen', ['lihat', 'ekspor']);
        $this->hak($role, 'sales_tidak_penjualan', ['tambah']);

        $this->jalankanMigrasi();

        $this->assertSame(
            ['lihat' => true, 'tambah' => true, 'ubah' => false, 'hapus' => false, 'ekspor' => true],
            array_map(fn ($v) => (bool) $v, (array) DB::table('role_hak')->where('role_id', $role)->where('menu', 'monitoring_harian_sales')->first(self::AKSI))
        );
        $this->assertSame(0, DB::table('role_hak')->whereIn('menu', ['checkin_pagi', 'sales_tidak_penjualan', 'sales_tidak_absen'])->count());
    }

    public function test_hak_menu_baru_yang_sudah_ada_tidak_berkurang(): void
    {
        $role = DB::table('roles')->insertGetId(['kode' => 'uji', 'nama' => 'Uji', 'created_at' => now(), 'updated_at' => now()]);
        $this->hak($role, 'monitoring_harian_sales', ['ubah']);
        $this->hak($role, 'checkin_pagi', ['lihat']);

        $this->jalankanMigrasi();

        $this->assertTrue(Akses::hakRole($role)['monitoring_harian_sales']['ubah']);
        $this->assertTrue(Akses::hakRole($role)['monitoring_harian_sales']['lihat']);
    }

    public function test_role_tanpa_hak_menu_lama_tidak_diubah(): void
    {
        $role = DB::table('roles')->insertGetId(['kode' => 'uji', 'nama' => 'Uji', 'created_at' => now(), 'updated_at' => now()]);
        $this->hak($role, 'provinsi', ['lihat']);

        $this->jalankanMigrasi();

        $this->assertSame(1, DB::table('role_hak')->where('role_id', $role)->where('menu', 'provinsi')->count());
        $this->assertSame(0, DB::table('role_hak')->where('role_id', $role)->where('menu', 'monitoring_harian_sales')->count());
    }
}
