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

    public function test_monitoring_harian_sales_queries_only_the_active_tab_table(): void
    {
        // Halaman bertab hanya meng-query tabel grup tab aktif; grup lain tidak ikut di-query (hemat COUNT/LIKE).
        $this->get('/monitoring_harian_sales')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Show')
                ->has('tables', 1)
                ->where('tables.0.name', 'checkin_pagi')
            );

        $this->get('/monitoring_harian_sales?tab=sales_tidak_penjualan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('tables', 1)
                ->where('tables.0.name', 'sales_tidak_penjualan')
            );
    }

    public function test_kpi_is_only_filled_for_table_with_kpi_configured(): void
    {
        $this->get('/monitoring_harian_sales?tab=sales_tidak_penjualan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.0.kpi', null)
            );
    }

    public function test_tanggal_filter_only_applies_to_table_with_date_filter(): void
    {
        Carbon::setTestNow('2026-10-23 08:00:00');

        DB::table('sales_tidak_penjualan')->insert([
            ['tanggal_data' => '2026-10-23', 'nama_sales' => 'Hari Ini'],
            ['tanggal_data' => '2026-10-22', 'nama_sales' => 'Kemarin'],
        ]);

        // Tab sales_tidak_penjualan tidak punya date_filter_column, jadi query tanggal diabaikan total.
        $this->get('/monitoring_harian_sales?tab=sales_tidak_penjualan&tanggal=2026-10-22')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 2)
                ->where('tanggal', null)
                ->where('hasDateFilter', false)
            );

        $this->get('/monitoring_harian_sales?tab=checkin_pagi&tanggal=2026-10-22')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
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
                ->has('tables', 2)
                ->where('tables.0.tab_key', 'gabungan')
                ->where('tables.1.tab_key', 'gabungan')
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
        // Halaman sendiri (RekapPencapaianSkuController) yang menghitung dari penjualan aplikasi motoris, bukan tabel laporan Marketama.
        $this->get('/rekap_pencapaian_sku')
            ->assertOk()
            ->assertViewIs('motoris.rekap-sku')
            ->assertViewHas('tab', 'motoris')
            ->assertSee('Avg/Day');
    }

    public function test_rekap_pencapaian_sku_tab_depo_follows_tab_query(): void
    {
        $this->get('/rekap_pencapaian_sku?tab=depo')
            ->assertOk()
            ->assertViewHas('tab', 'depo')
            ->assertSee('Avg/Day/Motoris');
    }

    public function test_slug_lama_rekap_pencapaian_sku_motoris_redirects_to_tab_motoris(): void
    {
        $this->get('/rekap_pencapaian_sku_motoris')
            ->assertRedirect('/rekap_pencapaian_sku?tab=motoris');
    }

    public function test_slug_lama_rekap_pencapaian_depo_sku_redirects_to_tab_depo(): void
    {
        $this->get('/rekap_pencapaian_depo_sku')
            ->assertRedirect('/rekap_pencapaian_sku?tab=depo');
    }

    public function test_redirect_slug_lama_rekap_pencapaian_sku_meneruskan_query_string_lain(): void
    {
        $this->get('/rekap_pencapaian_depo_sku?q=budi')
            ->assertRedirect('/rekap_pencapaian_sku?q=budi&tab=depo');
    }

    public function test_rekap_pencapaian_sku_is_a_single_sidebar_menu(): void
    {
        $slugs = collect(config('marketama.menu'))->pluck('items')->flatten(1)->pluck('slug');

        $this->assertSame(['rekap_pencapaian_sku'], $slugs->filter(fn ($slug) => str_starts_with($slug, 'rekap_pencapaian'))->values()->all());
    }

    public function test_motoris_report_default_tab_is_coverage(): void
    {
        // Halaman sendiri (MotorisReportController) yang menghitung dari tabel motoris, bukan tabel laporan Marketama.
        $this->get('/motoris_report')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/Report')
                ->where('tab', 'coverage')
                ->has('total'));
    }

    public function test_motoris_report_tab_performance_follows_tab_query(): void
    {
        $this->get('/motoris_report?tab=performance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/Report')
                ->where('tab', 'performance'));
    }

    public function test_motoris_report_tab_absensi_follows_tab_query(): void
    {
        $this->get('/motoris_report?tab=absensi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Motoris/Report')
                ->where('tab', 'absensi')
                ->has('baris.data'));
    }

    public function test_motoris_report_is_a_single_sidebar_menu(): void
    {
        $slugs = collect(config('marketama.menu'))->pluck('items')->flatten(1)->pluck('slug');

        $this->assertSame(
            ['motoris_report'],
            $slugs->filter(fn ($slug) => in_array($slug, ['motoris_report', 'motoris_coverage', 'motoris_performance', 'monitor_absensi_motoris'], true))->values()->all()
        );
    }

    public function test_slug_lama_motoris_coverage_redirects_to_tab_coverage(): void
    {
        $this->get('/motoris_coverage')
            ->assertRedirect('/motoris_report?tab=coverage');
    }

    public function test_slug_lama_motoris_performance_redirects_to_tab_performance(): void
    {
        $this->get('/motoris_performance')
            ->assertRedirect('/motoris_report?tab=performance');
    }

    public function test_slug_lama_monitor_absensi_motoris_redirects_to_tab_absensi(): void
    {
        $this->get('/monitor_absensi_motoris')
            ->assertRedirect('/motoris_report?tab=absensi');
    }

    public function test_redirect_slug_lama_motoris_meneruskan_query_string_lain(): void
    {
        $this->get('/motoris_performance?q=budi')
            ->assertRedirect('/motoris_report?q=budi&tab=performance');
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
