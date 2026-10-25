<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

// Menu user di footer sidebar: nama role lewat auth.user, dan section 'posisi' => 'footer' terfilter hak akses.
class SidebarFooterTest extends TestCase
{
    use RefreshDatabase;

    private function buatUserDenganHak(string $menu): User
    {
        $role = DB::table('roles')->insertGetId([
            'kode' => 'staf_footer', 'nama' => 'Staf Footer', 'bawaan' => false, 'cakupan' => 'semua',
            'login_web' => true, 'login_mobile' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('role_hak')->insert([
            'role_id' => $role, 'menu' => $menu,
            'lihat' => true, 'tambah' => false, 'ubah' => false, 'hapus' => false, 'ekspor' => false,
        ]);

        return User::factory()->create(['role_id' => $role]);
    }

    public function test_auth_user_memuat_nama_role(): void
    {
        $this->actingAs($this->buatUserDenganHak('pengumuman'));

        $this->get('/pengumuman')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.role', 'Staf Footer')
            );
    }

    public function test_menu_footer_hanya_memuat_item_yang_boleh_dilihat_role(): void
    {
        $this->actingAs($this->buatUserDenganHak('pengumuman'));

        $this->get('/pengumuman')
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu', function ($menu) {
                    $footer = collect($menu)->firstWhere('posisi', 'footer');

                    return collect($footer['items'] ?? [])->pluck('slug')->all() === ['pengumuman'];
                })
            );
    }
}
