<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SidebarCollapseFullTest extends TestCase
{
    use RefreshDatabase;

    private function renderSidebar(): string
    {
        return Blade::render('<x-ui.organisms.sidebar />');
    }

    public function test_brand_punya_tombol_toggle_collapse_seluruh_sidebar(): void
    {
        $html = $this->renderSidebar();

        $brandPos = strpos($html, 'class="brand"');
        $this->assertNotFalse($brandPos, 'Sidebar harus punya elemen .brand.');

        $toggleAttrPos = strpos($html, 'data-sidebar-toggle');
        $this->assertNotFalse($toggleAttrPos, 'Sidebar harus punya elemen dengan data-sidebar-toggle.');

        $navPos = strpos($html, 'class="nav ');
        $this->assertLessThan($navPos, $toggleAttrPos, 'Tombol toggle seharusnya ditaruh di dalam .brand, sebelum daftar nav.');
    }

    public function test_nav_link_punya_title_untuk_tooltip_native(): void
    {
        $html = $this->renderSidebar();

        $firstSection = config('marketama.menu')[0];
        $firstItem = $firstSection['items'][0];

        $this->assertStringContainsString('title="'.$firstItem['title'].'"', $html);
    }
}
