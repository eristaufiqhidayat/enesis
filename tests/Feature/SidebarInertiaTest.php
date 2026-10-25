<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

// Sidebar dirender Vue (Layouts/AppLayout.vue); data-nya datang dari shared props Inertia: menu, searchMenu, currentSlug.
class SidebarInertiaTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    private function buatUserDenganHak(string $menu): User
    {
        $role = DB::table('roles')->insertGetId([
            'kode' => 'staf_sidebar', 'nama' => 'Staf Sidebar', 'bawaan' => false, 'cakupan' => 'semua',
            'login_web' => true, 'login_mobile' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('role_hak')->insert([
            'role_id' => $role, 'menu' => $menu,
            'lihat' => true, 'tambah' => false, 'ubah' => false, 'hapus' => false, 'ekspor' => false,
        ]);

        return User::factory()->create(['role_id' => $role]);
    }

    /** Slug semua menu daun yang ada di prop menu (termasuk children). */
    private function slugDiMenu($menu): array
    {
        return collect($menu)
            ->flatMap(fn ($grup) => $grup['items'])
            ->flatMap(fn ($item) => $item['children'] ?? [$item])
            ->pluck('slug')
            ->all();
    }

    public function test_prop_menu_memuat_grup_dengan_judul_ikon_dan_item(): void
    {
        $this->get('/monitoring_harian_sales')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('menu', count(config('marketama.menu')))
                ->has('menu.0', fn (Assert $grup) => $grup
                    ->has('items')
                    ->etc())
            );
    }

    public function test_prop_menu_tidak_memuat_menu_yang_tidak_punya_hak(): void
    {
        $this->actingAs($this->buatUserDenganHak('loading_barang'));

        $this->get('/loading_barang')
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.0.items.0.slug', 'loading_barang')
                ->has('menu', 1)
                ->has('menu.0.items', 1)
            );
    }

    public function test_prop_search_menu_memuat_menu_termasuk_children(): void
    {
        $this->get('/home')
            ->assertInertia(fn (Assert $page) => $page
                ->where('searchMenu', fn ($menu) => collect($menu)->contains(
                    fn ($m) => $m['title'] === 'Monitoring Harian Sales' && $m['slug'] === 'monitoring_harian_sales'
                ))
            );
    }

    public function test_prop_search_menu_memuat_entri_tab_untuk_menu_lama(): void
    {
        $this->get('/home')
            ->assertInertia(fn (Assert $page) => $page
                ->where('searchMenu', fn ($menu) => collect($menu)->contains(
                    fn ($m) => $m['title'] === 'Sales Tidak Penjualan' && $m['slug'] === 'monitoring_harian_sales?tab=sales_tidak_penjualan'
                ))
            );
    }

    public function test_prop_search_menu_tidak_memuat_checkin_pagi_sebagai_menu_terpisah(): void
    {
        $this->get('/home')
            ->assertInertia(fn (Assert $page) => $page
                ->where('searchMenu', fn ($menu) => ! collect($menu)->contains(fn ($m) => $m['slug'] === 'checkin_pagi'))
            );
    }

    public function test_prop_search_menu_mengikuti_hak_akses(): void
    {
        $this->actingAs($this->buatUserDenganHak('loading_barang'));

        $this->get('/loading_barang')
            ->assertInertia(fn (Assert $page) => $page
                ->has('searchMenu', 1)
                ->where('searchMenu.0.slug', 'loading_barang')
            );
    }

    public function test_prop_current_slug_berisi_slug_menu_yang_sedang_dibuka(): void
    {
        $this->get('/monitoring_harian_sales')
            ->assertInertia(fn (Assert $page) => $page->where('currentSlug', 'monitoring_harian_sales'));
    }

    public function test_menu_sidebar_berisi_semua_slug_yang_dikonfigurasi_untuk_user_penuh(): void
    {
        $this->get('/home')
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu', fn ($menu) => collect($this->slugDiMenu($menu))
                    ->contains('monitoring_harian_sales'))
            );
    }
}
