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
}
