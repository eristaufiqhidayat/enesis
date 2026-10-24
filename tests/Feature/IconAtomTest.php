<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class IconAtomTest extends TestCase
{
    public function test_icon_atom_merender_svg_bukan_fas_fa(): void
    {
        $html = Blade::render('<x-ui.atoms.icon name="home" />');

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('fas fa-home', $html);
        $this->assertStringNotContainsString('<i ', $html);
    }

    public function test_atribut_tambahan_ter_merge_ke_elemen_svg_root(): void
    {
        $html = Blade::render('<x-ui.atoms.icon name="home" class="foo" />');

        $this->assertMatchesRegularExpression('/<svg[^>]*class="[^"]*\bfoo\b[^"]*"[^>]*>/', $html);
        $this->assertSame(1, preg_match_all('/class="/', $html), 'Elemen svg seharusnya hanya punya satu atribut class (ter-merge, bukan duplikat).');
    }

    public function test_isi_svg_sama_dengan_file_sumber(): void
    {
        $html = Blade::render('<x-ui.atoms.icon name="home" />');

        $sourceInner = $this->extractInner(file_get_contents(public_path('vendor/lucide/home.svg')));
        $renderedInner = $this->extractInner($html);

        $this->assertSame($sourceInner, $renderedInner);
    }

    private function extractInner(string $svg): string
    {
        preg_match('/<svg[^>]*>(.*)<\/svg>/s', $svg, $matches);

        return trim($matches[1] ?? '');
    }
}
