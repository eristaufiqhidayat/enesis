<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

// Contoh tabel menu umum dengan kolom angka: kategori_user (permissions). Menu Kategori Produk kini modul CRUD sendiri.
class MenuFilterAngkaTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('kategori_user')->insert([
            ['scope_data' => 'A', 'nama_kategori' => 'Kecil', 'permissions' => 500],
            ['scope_data' => 'B', 'nama_kategori' => 'Sedang', 'permissions' => 1000],
            ['scope_data' => 'C', 'nama_kategori' => 'Besar', 'permissions' => 20000],
        ]);
    }

    public function test_operator_kurang_dari_pada_kolom_angka(): void
    {
        $this->get('/kategori_user?filter[kategori_user][permissions]=1000&operator[kategori_user][permissions]=<')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.nama_kategori', 'Kecil')
            ));
    }

    public function test_operator_lebih_dari_sama_dengan(): void
    {
        $this->get('/kategori_user?filter[kategori_user][permissions]=1000&operator[kategori_user][permissions]=>%3D')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 2)
            ));
    }

    public function test_tanpa_operator_kolom_angka_memakai_sama_dengan(): void
    {
        $this->get('/kategori_user?filter[kategori_user][permissions]=1000')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.nama_kategori', 'Sedang')
                ->where('tables.0.filter_operator', ['source_id' => '=', 'permissions' => '='])
                ->where('tables.0.filter_angka', ['source_id', 'permissions'])
            ));
    }

    public function test_operator_tidak_dikenal_dianggap_sama_dengan(): void
    {
        // Operator dari input mentah tidak pernah langsung masuk SQL; yang tidak ada di whitelist jadi '='.
        $this->get('/kategori_user?filter[kategori_user][permissions]=1000&operator[kategori_user][permissions]=DROP')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.filter_operator', ['source_id' => '=', 'permissions' => '='])
            ));
    }

    public function test_nilai_bukan_angka_di_kolom_angka_diabaikan(): void
    {
        $this->get('/kategori_user?filter[kategori_user][permissions]=abc&operator[kategori_user][permissions]=<')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 3)
                ->where('tables.0.filter_kolom', [])
            ));
    }

    public function test_kolom_teks_tetap_memakai_pencocokan_teks(): void
    {
        $this->get('/kategori_user?filter[kategori_user][nama_kategori]=xyz')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 0)
                ->where('tables.0.filter_angka', ['source_id', 'permissions'])
            ));
    }
}
