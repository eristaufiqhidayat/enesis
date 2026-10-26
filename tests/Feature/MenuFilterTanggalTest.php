<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class MenuFilterTanggalTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('lap_user')->insert([
            ['nickname' => 'Satu', 'created_at' => '2026-10-22 08:00:00'],
            ['nickname' => 'Dua', 'created_at' => '2026-10-23 09:30:00'],
            ['nickname' => 'Tiga', 'created_at' => '2026-10-24 17:45:00'],
        ]);
    }

    public function test_tanggal_sama_dengan_mencocokkan_seluruh_hari(): void
    {
        // Kolom datetime: jam berapa pun di hari yang dipilih ikut cocok.
        $this->get('/lap_user?filter[lap_user][created_at]=2026-10-23')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.nickname', 'Dua')
                ->where('tables.0.filter_tanggal', ['created_at', 'updated_at'])
                ->where('tables.0.filter_operator.created_at', '=')
            ));
    }

    public function test_operator_sebelum_dan_sesudah_tanggal(): void
    {
        $this->get('/lap_user?filter[lap_user][created_at]=2026-10-23&operator[lap_user][created_at]=<')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.nickname', 'Satu')
            ));

        $this->get('/lap_user?filter[lap_user][created_at]=2026-10-23&operator[lap_user][created_at]=>%3D')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 2)
            ));
    }

    public function test_operator_sampai_dan_selain_tanggal(): void
    {
        $this->get('/lap_user?filter[lap_user][created_at]=2026-10-23&operator[lap_user][created_at]=<%3D')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 2)
            ));

        $this->get('/lap_user?filter[lap_user][created_at]=2026-10-23&operator[lap_user][created_at]=!%3D')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 2)
                ->where('tables.0.rows.data.0.nickname', 'Satu')
            ));
    }

    public function test_tanggal_tidak_valid_diabaikan(): void
    {
        // 2026-02-31 tidak ada; format selain Y-m-d juga diabaikan.
        $this->get('/lap_user?filter[lap_user][created_at]=2026-02-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 3)
                ->where('tables.0.filter_kolom', [])
            ));

        $this->get('/lap_user?filter[lap_user][created_at]=23-10-2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 3)
            ));
    }

    public function test_kolom_bertipe_teks_tidak_memakai_datepicker(): void
    {
        $this->get('/lap_user?filter[lap_user][nickname]=2026-10-23')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $page) => $page
                ->where('tables.0.filter_tanggal', ['created_at', 'updated_at'])
                ->where('tables.0.rows.total', 0)
            ));
    }
}
