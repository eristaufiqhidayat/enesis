<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    public function test_root_redirects_to_home(): void
    {
        $this->get('/')->assertRedirect('/home');
    }

    public function test_every_sidebar_menu_page_loads(): void
    {
        foreach (array_keys(config('marketama.pages')) as $slug) {
            $this->get("/{$slug}")->assertOk();
        }
    }

    public function test_menu_page_lists_table_rows(): void
    {
        DB::table('provinsi')->insert(['kode' => '32', 'nama_provinsi' => 'Jawa Barat']);

        $this->get('/provinsi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Show')
                ->where('tables.0.rows.data.0.nama_provinsi', 'Jawa Barat')
                ->where('tables.0.rows.total', 1)
            );
    }

    public function test_monitoring_harian_sales_returns_three_tables_by_name(): void
    {
        $this->get('/monitoring_harian_sales')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Show')
                ->has('tables', 3)
                ->where('tables.0.name', 'checkin_pagi')
                ->where('tables.1.name', 'sales_tidak_penjualan')
                ->where('tables.2.name', 'sales_tidak_absen')
            );
    }

    public function test_kpi_is_only_filled_for_table_with_kpi_configured(): void
    {
        $this->get('/monitoring_harian_sales')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.1.kpi', null)
                ->where('tables.2.kpi', null)
            );
    }

    public function test_tanggal_filter_only_applies_to_table_with_date_filter(): void
    {
        Carbon::setTestNow('2026-10-23 08:00:00');

        DB::table('sales_tidak_penjualan')->insert([
            ['tanggal_data' => '2026-10-23', 'nama_sales' => 'Hari Ini'],
            ['tanggal_data' => '2026-10-22', 'nama_sales' => 'Kemarin'],
        ]);

        $this->get('/monitoring_harian_sales?tanggal=2026-10-22')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.1.rows.total', 2)
                ->where('tanggal', '2026-10-22')
                ->where('hasDateFilter', true)
            );
    }

    public function test_active_tab_defaults_to_first_table_when_tab_is_empty(): void
    {
        $this->get('/monitoring_harian_sales')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'checkin_pagi')
            );
    }

    public function test_active_tab_follows_valid_tab_query(): void
    {
        $this->get('/monitoring_harian_sales?tab=sales_tidak_absen')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'sales_tidak_absen')
            );
    }

    public function test_active_tab_falls_back_to_default_when_tab_query_is_invalid(): void
    {
        $this->get('/monitoring_harian_sales?tab=tidak_ada')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'checkin_pagi')
            );
    }

    public function test_satu_tab_bisa_berisi_lebih_dari_satu_tabel(): void
    {
        // Konfigurasi tab diganti sementara: satu grup berisi dua tabel, grup lain berisi satu tabel.
        config(['marketama.pages.monitoring_harian_sales.tables' => [
            ['tab_key' => 'gabungan', 'tab_label' => 'Gabungan', 'tables' => ['checkin_pagi', 'sales_tidak_penjualan']],
            ['tab_key' => 'lain', 'tab_label' => 'Lain', 'tables' => ['sales_tidak_absen']],
        ]]);

        $this->get('/monitoring_harian_sales?tab=gabungan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'gabungan')
                ->has('tables', 3)
                ->where('tables.0.tab_key', 'gabungan')
                ->where('tables.1.tab_key', 'gabungan')
                ->where('tables.2.tab_key', 'lain')
            );
    }

    public function test_slug_lama_checkin_pagi_redirects_to_tab_checkin(): void
    {
        $this->get('/checkin_pagi')
            ->assertRedirect('/monitoring_harian_sales?tab=checkin_pagi');
    }

    public function test_slug_lama_sales_tidak_penjualan_redirects_to_tab_tidak_penjualan(): void
    {
        $this->get('/sales_tidak_penjualan')
            ->assertRedirect('/monitoring_harian_sales?tab=sales_tidak_penjualan');
    }

    public function test_slug_lama_sales_tidak_absen_redirects_to_tab_tidak_absen(): void
    {
        $this->get('/sales_tidak_absen')
            ->assertRedirect('/monitoring_harian_sales?tab=sales_tidak_absen');
    }

    public function test_redirect_slug_lama_meneruskan_query_string_lain(): void
    {
        $this->get('/checkin_pagi?q=budi&tanggal=2026-10-22')
            ->assertRedirect('/monitoring_harian_sales?q=budi&tanggal=2026-10-22&tab=checkin_pagi');
    }

    public function test_redirect_slug_lama_ditolak_untuk_role_tanpa_hak_menu_tujuan(): void
    {
        // Hak dicek di hop redirect ini juga (bukan hanya di halaman tujuan), sama seperti rute menu lain.
        $roleId = DB::table('roles')->insertGetId([
            'kode' => 'tanpa_monitoring', 'nama' => 'Tanpa Monitoring', 'bawaan' => false, 'cakupan' => 'semua',
            'login_web' => true, 'login_mobile' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_hak')->insert([
            'role_id' => $roleId, 'menu' => 'home', 'lihat' => true, 'tambah' => false, 'ubah' => false, 'hapus' => false, 'ekspor' => false,
        ]);
        $this->actingAs(User::factory()->make(['id' => 2, 'role_id' => $roleId, 'aktif' => true, 'wajib_ganti_password' => false]));

        $this->get('/checkin_pagi')->assertForbidden();
    }

    public function test_rekap_pencapaian_sku_default_tab_is_motoris(): void
    {
        $this->get('/rekap_pencapaian_sku')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Show')
                ->where('activeTab', 'motoris')
                ->has('tables', 4)
                ->where('tables.0.name', 'rekap_pencapaian_sku_motoris_tableachieved')
                ->where('tables.1.name', 'rekap_pencapaian_sku_motoris_tablenotachieved')
                ->where('tables.2.name', 'rekap_pencapaian_depo_sku_tableachieved')
                ->where('tables.3.name', 'rekap_pencapaian_depo_sku_tablenotachieved')
                ->where('tables.0.tab_key', 'motoris')
                ->where('tables.1.tab_key', 'motoris')
                ->where('tables.2.tab_key', 'depo')
                ->where('tables.3.tab_key', 'depo')
            );
    }

    public function test_rekap_pencapaian_sku_tab_depo_follows_tab_query(): void
    {
        $this->get('/rekap_pencapaian_sku?tab=depo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeTab', 'depo')
                ->has('tables', 4)
                ->where('tables.2.tab_key', 'depo')
                ->where('tables.3.tab_key', 'depo')
            );
    }

    public function test_rekap_pencapaian_sku_is_a_single_sidebar_menu(): void
    {
        $slugs = collect(config('marketama.menu'))->pluck('items')->flatten(1)->pluck('slug');

        $this->assertSame(['rekap_pencapaian_sku'], $slugs->filter(fn ($slug) => str_starts_with($slug, 'rekap_pencapaian'))->values()->all());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unknown_menu_returns_404(): void
    {
        $this->get('/tidak_ada')->assertNotFound();
    }
}
