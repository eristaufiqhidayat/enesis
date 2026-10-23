<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckinPagiFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-23 08:00:00');

        DB::table('checkin_pagi')->insert([
            ['tanggal_data' => '2026-10-23', 'nama_sales' => 'Budi Hari Ini'],
            ['tanggal_data' => '2026-10-22', 'nama_sales' => 'Andi Kemarin'],
            ['tanggal_data' => '2026-10-22', 'nama_sales' => 'Citra Kemarin'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_default_shows_only_rows_for_today(): void
    {
        $this->get('/checkin_pagi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.nama_sales', 'Budi Hari Ini')
                ->where('tanggal', '2026-10-23')
                ->where('hasDateFilter', true)
            );
    }

    public function test_explicit_tanggal_shows_only_rows_for_that_date(): void
    {
        $this->get('/checkin_pagi?tanggal=2026-10-22')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 2)
                ->where('tables.0.rows.data.0.nama_sales', 'Andi Kemarin')
                ->where('tables.0.rows.data.1.nama_sales', 'Citra Kemarin')
                ->where('tanggal', '2026-10-22')
            );
    }

    public function test_tanggal_combines_with_text_search(): void
    {
        $this->get('/checkin_pagi?tanggal=2026-10-22&q=Citra')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.nama_sales', 'Citra Kemarin')
            );
    }

    public function test_invalid_tanggal_falls_back_to_today(): void
    {
        $this->get('/checkin_pagi?tanggal=bukan-tanggal')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tanggal', '2026-10-23')
            );

        $this->get('/checkin_pagi?tanggal=2026-10-1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tanggal', '2026-10-23')
            );
    }

    public function test_page_without_date_filter_is_not_affected(): void
    {
        DB::table('provinsi')->insert(['kode' => '32', 'nama_provinsi' => 'Jawa Barat']);

        $this->get('/provinsi?tanggal=2026-10-22')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 1)
                ->where('tanggal', null)
                ->where('hasDateFilter', false)
            );
    }
}
