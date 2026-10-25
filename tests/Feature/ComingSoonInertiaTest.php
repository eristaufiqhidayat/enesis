<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

// Placeholder "Tambah Loading Barang" dan "Tambah Barang Canvassing" dirender lewat Inertia.
class ComingSoonInertiaTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    private function buatUserDenganHak(array $aksi): User
    {
        $role = DB::table('roles')->insertGetId([
            'kode' => 'staf_uji', 'nama' => 'Staf Uji', 'bawaan' => false, 'cakupan' => 'semua',
            'login_web' => true, 'login_mobile' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('role_hak')->insert([
            'role_id' => $role, 'menu' => 'loading_barang',
            'lihat' => in_array('lihat', $aksi), 'tambah' => in_array('tambah', $aksi),
            'ubah' => false, 'hapus' => false, 'ekspor' => false,
        ]);

        return User::factory()->create(['role_id' => $role]);
    }

    public function test_placeholder_loading_barang_dirender_inertia(): void
    {
        $this->get('/loading_barang/add')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/ComingSoon')
                ->where('title', 'Tambah Loading Barang')
                ->where('back', 'loading_barang')
                ->where('backTitle', config('marketama.pages.loading_barang.title')));
    }

    public function test_placeholder_barang_canvassing_dirender_inertia(): void
    {
        $this->get('/barang_canvassing/add')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/ComingSoon')
                ->where('title', 'Tambah Barang Canvassing')
                ->where('back', 'barang_canvassing')
                ->where('backTitle', config('marketama.pages.barang_canvassing.title')));
    }

    public function test_user_tanpa_hak_tambah_tidak_bisa_membuka_placeholder(): void
    {
        $this->actingAs($this->buatUserDenganHak(['lihat']));

        $this->get('/loading_barang/add')->assertForbidden();
        $this->get('/barang_canvassing/add')->assertForbidden();
    }

    public function test_user_dengan_hak_tambah_bisa_membuka_placeholder(): void
    {
        $this->actingAs($this->buatUserDenganHak(['lihat', 'tambah']));

        $this->get('/loading_barang/add')->assertOk();
    }

    public function test_view_blade_coming_soon_sudah_dihapus(): void
    {
        $this->assertFalse(View::exists('menu.coming_soon'));
    }
}
