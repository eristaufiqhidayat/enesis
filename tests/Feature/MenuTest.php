<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_home(): void
    {
        $this->get('/')->assertRedirect('/home');
    }

    public function test_every_sidebar_menu_page_loads(): void
    {
        foreach (array_keys(config('marketama.pages')) as $slug) {
            $this->get("/{$slug}")->assertOk();
        }
    }

    public function test_menu_page_lists_table_rows(): void
    {
        DB::table('provinsi')->insert(['kode' => '32', 'nama_provinsi' => 'Jawa Barat']);

        $this->get('/provinsi')
            ->assertOk()
            ->assertSee('Jawa Barat')
            ->assertSee('Total 1 data');
    }

    public function test_unknown_menu_returns_404(): void
    {
        $this->get('/tidak_ada')->assertNotFound();
    }
}
