<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SidebarFlyoutTest extends TestCase
{
    use RefreshDatabase;

    private function renderSidebar(): string
    {
        return Blade::render('<x-ui.organisms.sidebar />');
    }

    public function test_item_ber_children_memuat_toggle_dan_panel_flyout(): void
    {
        $html = $this->renderSidebar();

        $parents = collect(config('marketama.menu'))
            ->flatMap(fn ($section) => $section['items'])
            ->filter(fn ($item) => isset($item['children']));

        $this->assertGreaterThan(0, $parents->count());

        foreach ($parents as $item) {
            $this->assertStringContainsString(
                'id="'.$item['id'].'" data-flyout-panel',
                $html,
                "Panel flyout untuk '{$item['title']}' seharusnya memakai data-flyout-panel."
            );
        }

        $this->assertSame(
            $parents->count(),
            preg_match_all('/<a [^>]*data-flyout-toggle/', $html),
            'Setiap item ber-children seharusnya punya tepat satu data-flyout-toggle.'
        );
    }

    public function test_submenu_tidak_lagi_memakai_accordion_collapse_generik(): void
    {
        $html = $this->renderSidebar();

        foreach (config('marketama.menu') as $section) {
            foreach ($section['items'] as $item) {
                if (! isset($item['children'])) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    'data-collapse-toggle="'.$item['id'].'"',
                    $html,
                    "Item '{$item['title']}' seharusnya tidak lagi memakai data-collapse-toggle."
                );
            }
        }
    }

    public function test_parent_yang_berisi_menu_aktif_diberi_indikator_walau_flyout_tertutup(): void
    {
        $child = collect(config('marketama.menu'))
            ->flatMap(fn ($section) => $section['items'])
            ->first(fn ($item) => isset($item['children']))['children'][0];

        $html = Blade::render(
            '<x-ui.organisms.partials.sidebar-nav-items :items="$items" :current="$current" />',
            [
                'items' => [[
                    'title' => 'Induk',
                    'id' => 'induk-test',
                    'children' => [['slug' => $child['slug'], 'title' => $child['title'], 'icon' => null]],
                ]],
                'current' => $child['slug'],
            ]
        );

        $this->assertStringContainsString('nav-link is-open has-active-child', $html);
        $this->assertStringContainsString('nav-flyout is-open', $html);
    }

    public function test_flyout_rekursif_untuk_children_di_dalam_children(): void
    {
        $html = Blade::render(
            '<x-ui.organisms.partials.sidebar-nav-items :items="$items" :current="$current" />',
            [
                'items' => [[
                    'title' => 'Level 1',
                    'id' => 'level-1',
                    'children' => [[
                        'title' => 'Level 2',
                        'id' => 'level-2',
                        'children' => [['slug' => 'level-3-menu', 'title' => 'Level 3', 'icon' => null]],
                    ]],
                ]],
                'current' => null,
            ]
        );

        $this->assertSame(2, substr_count($html, 'data-flyout-panel'));
        $this->assertSame(2, substr_count($html, 'data-flyout-toggle'));
        $this->assertStringContainsString('id="level-2" data-flyout-panel', $html);
        $this->assertStringContainsString('Level 3', $html);
    }
}
