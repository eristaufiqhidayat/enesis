<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class MenuFilterAngkaTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('kategori_produk')->insert([
            ['kode' => 'A', 'nama' => 'Kecil', 'total_produk' => 500],
            ['kode' => 'B', 'nama' => 'Sedang', 'total_produk' => 1000],
            ['kode' => 'C', 'nama' => 'Besar', 'total_produk' => 20000],
        ]);
    }

    public function test_operator_kurang_dari_pada_kolom_angka(): void
    {
        $this->get('/kategori_produk?filter[kategori_produk][total_produk]=1000&operator[kategori_produk][total_produk]=<')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.nama', 'Kecil')
            ));
    }

    public function test_operator_lebih_dari_sama_dengan(): void
    {
        $this->get('/kategori_produk?filter[kategori_produk][total_produk]=1000&operator[kategori_produk][total_produk]=>%3D')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 2)
            ));
    }

    public function test_tanpa_operator_kolom_angka_memakai_sama_dengan(): void
    {
        $this->get('/kategori_produk?filter[kategori_produk][total_produk]=1000')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.nama', 'Sedang')
                ->where('tables.0.filter_operator', ['total_produk' => '='])
                ->where('tables.0.filter_angka', ['total_produk'])
            ));
    }

    public function test_operator_tidak_dikenal_dianggap_sama_dengan(): void
    {
        // Operator dari input mentah tidak pernah langsung masuk SQL; yang tidak ada di whitelist jadi '='.
        $this->get('/kategori_produk?filter[kategori_produk][total_produk]=1000&operator[kategori_produk][total_produk]=DROP')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.filter_operator', ['total_produk' => '='])
            ));
    }

    public function test_nilai_bukan_angka_di_kolom_angka_diabaikan(): void
    {
        $this->get('/kategori_produk?filter[kategori_produk][total_produk]=abc&operator[kategori_produk][total_produk]=<')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 3)
                ->where('tables.0.filter_kolom', [])
            ));
    }

    public function test_kolom_teks_tetap_memakai_pencocokan_teks(): void
    {
        $this->get('/kategori_produk?filter[kategori_produk][nama]=xyz')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 0)
                ->where('tables.0.filter_angka', ['total_produk'])
            ));
    }
}
