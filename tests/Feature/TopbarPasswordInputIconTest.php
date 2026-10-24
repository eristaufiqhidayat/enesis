<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TopbarPasswordInputIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_topbar_tidak_lagi_mengandung_font_awesome(): void
    {
        $html = Blade::render('<x-ui.organisms.topbar />');

        $this->assertStringNotContainsString('fas fa-', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_topbar_tombol_shortcut_dan_notifikasi_merender_svg(): void
    {
        $html = Blade::render('<x-ui.organisms.topbar />');

        $this->assertGreaterThanOrEqual(5, substr_count($html, '<svg'));
    }

    public function test_password_input_tidak_lagi_mengandung_font_awesome(): void
    {
        $html = Blade::render('<x-ui.molecules.password-input id="password" name="password" />');

        $this->assertStringNotContainsString('fas fa-eye', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_password_input_punya_dua_ikon_mata_untuk_toggle(): void
    {
        $html = Blade::render('<x-ui.molecules.password-input id="password" name="password" />');

        $this->assertSame(2, substr_count($html, '<svg'));
        $this->assertStringContainsString('hidden', $html);
    }
}
