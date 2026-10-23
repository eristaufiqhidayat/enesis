<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BreadcrumbTest extends TestCase
{
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
}
