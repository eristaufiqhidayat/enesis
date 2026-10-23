<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BreadcrumbTest extends TestCase
{
    use RefreshDatabase;

    private function renderBreadcrumb(string $slug): string
    {
        return Blade::render('<x-ui.molecules.breadcrumb :slug="$slug" />', ['slug' => $slug]);
    }

    public function test_menu_tanpa_grup_menampilkan_satu_level(): void
    {
        $html = $this->renderBreadcrumb('home');

        $this->assertStringContainsString('breadcrumb-item">Beranda</span>', $html);
        $this->assertStringNotContainsString('breadcrumb-separator', $html);
    }

    public function test_menu_di_dalam_grup_menampilkan_dua_level(): void
    {
        $html = $this->renderBreadcrumb('loading_barang');

        $this->assertStringContainsString('breadcrumb-item">Warehouse</span>', $html);
        $this->assertStringContainsString('breadcrumb-item">Loading Barang</span>', $html);
        $this->assertStringContainsString('breadcrumb-separator', $html);
    }

    public function test_menu_children_menampilkan_tiga_level(): void
    {
        $html = $this->renderBreadcrumb('lap_checkin_checkout');

        $this->assertStringContainsString('breadcrumb-item">Laporan</span>', $html);
        $this->assertStringContainsString('breadcrumb-item">Lap. Checkin Checkout</span>', $html);
        $this->assertSame(2, substr_count($html, 'breadcrumb-separator'));
    }

    public function test_slug_tidak_match_tidak_menampilkan_breadcrumb(): void
    {
        $html = $this->renderBreadcrumb('tidak_ada_di_menu');

        $this->assertStringNotContainsString('class="breadcrumb"', $html);
    }

    public function test_coming_soon_loading_barang_menampilkan_breadcrumb_menu_asal(): void
    {
        $this->get('/loading_barang/add')
            ->assertOk()
            ->assertSee('breadcrumb-item">Warehouse</span>', false)
            ->assertSee('breadcrumb-item">Loading Barang</span>', false);
    }

    public function test_coming_soon_barang_canvassing_menampilkan_breadcrumb_menu_asal(): void
    {
        $this->get('/barang_canvassing/add')
            ->assertOk()
            ->assertSee('breadcrumb-item">Canvassing</span>', false)
            ->assertSee('breadcrumb-item">Barang Canvassing</span>', false);
    }

    public function test_tracking_koordinator_menampilkan_breadcrumb_yang_benar(): void
    {
        $this->get('/tracking_koordinator_history_location')
            ->assertOk()
            ->assertSee('breadcrumb-item">Canvassing</span>', false)
            ->assertSee('breadcrumb-item">Tracking History Koordinator Location</span>', false);
    }

    public function test_tracking_sales_menampilkan_breadcrumb_yang_benar(): void
    {
        $this->get('/tracking_sales_history_location')
            ->assertOk()
            ->assertSee('breadcrumb-item">Canvassing</span>', false)
            ->assertSee('breadcrumb-item">Tracking History Sales Location</span>', false);
    }

    public function test_design_area_menampilkan_breadcrumb_master_area(): void
    {
        $this->get('/master_area/design_area')
            ->assertOk()
            ->assertSee('breadcrumb-item">Master Data &amp; Pengaturan</span>', false)
            ->assertSee('breadcrumb-item">Konfirgurasi</span>', false)
            ->assertSee('breadcrumb-item">Master Area</span>', false);
    }
}
