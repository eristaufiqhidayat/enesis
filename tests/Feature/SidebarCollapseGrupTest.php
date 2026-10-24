<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SidebarCollapseGrupTest extends TestCase
{
    use RefreshDatabase;

    private function renderSidebar(): string
    {
        return Blade::render('<x-ui.organisms.sidebar />');
    }

    public function test_grup_berlabel_punya_toggle_collapse(): void
    {
        $html = $this->renderSidebar();

        foreach (config('marketama.menu') as $index => $section) {
            if (! $section['label']) {
                continue;
            }

            $this->assertStringContainsString(
                'data-collapse-toggle="group-'.$index.'"',
                $html,
                "Grup '{$section['label']}' seharusnya punya data-collapse-toggle."
            );
        }
    }

    public function test_grup_pertama_tanpa_label_tidak_punya_toggle(): void
    {
        $html = $this->renderSidebar();
        $firstSection = config('marketama.menu')[0];

        $this->assertNull($firstSection['label']);
        $this->assertStringNotContainsString('data-collapse-toggle="group-0"', $html);
    }

    public function test_tombol_toggle_semua_grup_ada_di_brand_bersebelahan_dengan_sidebar_toggle(): void
    {
        $html = $this->renderSidebar();

        $this->assertMatchesRegularExpression(
            '/<div class="brand">.*?<button[^>]*data-toggle-all-groups[^>]*>.*?<\/button>\s*<button[^>]*data-sidebar-toggle[^>]*>.*?<ul class="nav/s',
            $html,
            'Tombol toggle semua grup seharusnya di .brand, tepat di sebelum tombol sidebar-toggle.'
        );
    }

    public function test_setiap_grup_berlabel_punya_chevron_dan_wrapper_ul(): void
    {
        $html = $this->renderSidebar();

        $labeledGroupCount = collect(config('marketama.menu'))->filter(fn ($section) => $section['label'])->count();

        $this->assertSame(
            $labeledGroupCount,
            substr_count($html, 'class="section-label')
        );
        $this->assertGreaterThanOrEqual($labeledGroupCount, substr_count($html, 'nav-chevron'));
    }

    public function test_sidebar_tidak_lagi_mengandung_font_awesome(): void
    {
        $html = $this->renderSidebar();

        $this->assertStringNotContainsString('fas fa-', $html);
        $this->assertStringNotContainsString('far fa-', $html);
    }

    public function test_jumlah_svg_ikon_menu_sesuai_jumlah_entri_berikon(): void
    {
        $html = $this->renderSidebar();

        $jumlahEntriBerikon = 0;
        foreach (config('marketama.menu') as $section) {
            foreach ($section['items'] as $item) {
                if ($item['icon'] !== null) {
                    $jumlahEntriBerikon++;
                }

                foreach ($item['children'] ?? [] as $child) {
                    if ($child['icon'] !== null) {
                        $jumlahEntriBerikon++;
                    }
                }
            }
        }

        $this->assertSame($jumlahEntriBerikon, substr_count($html, 'nav-link-icon-svg'));
        $this->assertGreaterThan(0, substr_count($html, '<svg'));
    }
}
