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

    public function test_grup_berlabel_dirender_sebagai_flyout_bukan_collapse(): void
    {
        $html = $this->renderSidebar();

        $labeledGroupCount = collect(config('marketama.menu'))->filter(fn ($section) => $section['label'])->count();

        $this->assertSame(
            $labeledGroupCount,
            preg_match_all('/<li class="nav-item has-flyout section-group">\s*<div class="section-label[\s"][^>]*data-flyout-toggle/', $html),
            'Setiap grup berlabel seharusnya berupa flyout toggle (data-flyout-toggle).'
        );
        $this->assertStringNotContainsString('data-collapse-toggle', $html);
        $this->assertStringNotContainsString('sidebar-collapsed-groups', $html);
    }

    public function test_tombol_toggle_semua_grup_sudah_dihapus(): void
    {
        $html = $this->renderSidebar();

        $this->assertStringNotContainsString('data-toggle-all-groups', $html);
        $this->assertStringContainsString('data-sidebar-toggle', $html);
    }

    public function test_grup_tanpa_label_tetap_tampil_langsung_tanpa_flyout(): void
    {
        $html = $this->renderSidebar();
        $firstSection = config('marketama.menu')[0];

        $this->assertNull($firstSection['label']);

        // Flyout toggle hanya untuk grup berlabel dan item ber-children; grup tanpa label tidak ikut.
        $labeledGroupCount = collect(config('marketama.menu'))->filter(fn ($section) => $section['label'])->count();
        // Spasi di depan membedakan atribut di markup dari selector "[data-flyout-toggle]" di blok JS.
        $this->assertSame(
            $labeledGroupCount + $this->submenuCount(),
            substr_count($html, ' data-flyout-toggle')
        );
    }

    public function test_sidebar_tidak_lagi_mengandung_font_awesome(): void
    {
        $html = $this->renderSidebar();

        $this->assertStringNotContainsString('fas fa-', $html);
        $this->assertStringNotContainsString('far fa-', $html);
    }

    public function test_ikon_sidebar_hanya_di_header_grup_header_submenu_dan_item_daun_grup_tanpa_label(): void
    {
        $html = $this->renderSidebar();

        $labeledGroupCount = collect(config('marketama.menu'))->filter(fn ($section) => $section['label'])->count();

        // Ikon item daun (nav-link-icon-svg) hanya untuk grup pertama yang tanpa label (Beranda dkk.) —
        // item daun di dalam panel flyout grup berlabel/submenu lain tidak ikut berikon meski config-nya
        // punya key icon, supaya tidak semua submenu tiba-tiba berikon.
        $allItems = collect(config('marketama.menu'))->flatMap(fn ($section) => $section['items'])->all();
        $submenusWithIcon = $this->countItemsWithChildrenAndIcon($allItems);
        $firstSectionItems = config('marketama.menu')[0]['items'];
        $this->assertSame($this->countLeafItemsWithIcon($firstSectionItems), substr_count($html, 'nav-link-icon-svg'));
        $this->assertSame($labeledGroupCount * 2 + $submenusWithIcon, substr_count($html, 'section-label-icon'));
    }

    private function submenuCount(): int
    {
        return collect(config('marketama.menu'))
            ->flatMap(fn ($section) => $section['items'])
            ->filter(fn ($item) => isset($item['children']))
            ->count();
    }

    private function countItemsWithChildrenAndIcon(array $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            if (isset($item['children'])) {
                if (! empty($item['icon'])) {
                    $count++;
                }
                $count += $this->countItemsWithChildrenAndIcon($item['children']);
            }
        }

        return $count;
    }

    private function countLeafItemsWithIcon(array $items): int
    {
        $count = 0;

        foreach ($items as $item) {
            if (isset($item['children'])) {
                $count += $this->countLeafItemsWithIcon($item['children']);
            } elseif (! empty($item['icon'])) {
                $count++;
            }
        }

        return $count;
    }
}
