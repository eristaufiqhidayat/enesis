<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SidebarSearchTest extends TestCase
{
    use RefreshDatabase;

    private function renderSidebar(): string
    {
        return Blade::render('<x-ui.organisms.sidebar />');
    }

    /** Potongan HTML di dalam .brand (dari pembukanya sampai .sidebar-scroll). */
    private function brandHtml(string $html): string
    {
        $this->assertMatchesRegularExpression('/<div class="brand">.*?<div class="sidebar-scroll">/s', $html);
        preg_match('/<div class="brand">(.*?)<div class="sidebar-scroll">/s', $html, $cocok);

        return $cocok[1];
    }

    public function test_input_pencarian_ada_di_dalam_brand(): void
    {
        $brand = $this->brandHtml($this->renderSidebar());

        $this->assertMatchesRegularExpression('/<input[^>]*data-sidebar-search[^>]*>/', $brand);
        $this->assertStringContainsString('placeholder="Cari menu..."', $brand);
    }

    public function test_dropdown_hasil_ada_di_dalam_brand_dan_awalnya_tersembunyi(): void
    {
        $brand = $this->brandHtml($this->renderSidebar());

        $this->assertMatchesRegularExpression(
            '/<ul[^>]*data-sidebar-search-results[^>]*class="sidebar-search-results hidden"[^>]*>/',
            $brand
        );
    }

    public function test_input_pencarian_di_bawah_tombol_collapse(): void
    {
        $brand = $this->brandHtml($this->renderSidebar());

        $this->assertMatchesRegularExpression(
            '/data-sidebar-toggle.*?<\/button>\s*<\/div>\s*<div class="sidebar-search">.*?data-sidebar-search/s',
            $brand
        );
    }

    public function test_data_pencarian_memuat_seluruh_menu_termasuk_children(): void
    {
        $html = $this->renderSidebar();

        preg_match('/const searchMenus = (\[.*?\]);\n/s', $html, $cocok);
        $this->assertNotEmpty($cocok, 'Data pencarian menu seharusnya di-embed ke script.');

        $menu = json_decode($cocok[1], true);

        $jumlahDiharapkan = 0;
        foreach (config('marketama.menu') as $section) {
            foreach ($section['items'] as $item) {
                $jumlahDiharapkan += isset($item['children']) ? count($item['children']) : 1;
            }
        }

        $this->assertCount($jumlahDiharapkan, $menu);
        $this->assertContains(
            ['title' => 'Sales Checkin Outlet Pagi', 'slug' => 'checkin_pagi'],
            $menu
        );
    }
}
