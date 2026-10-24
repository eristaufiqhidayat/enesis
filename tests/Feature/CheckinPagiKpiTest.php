<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class CheckinPagiKpiTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-23 08:00:00');

        DB::table('checkin_pagi')->insert([
            ['tanggal_data' => '2026-10-23', 'nama_sales' => 'Budi', 'checkin_pertama_hari_ini' => '08:05'],
            ['tanggal_data' => '2026-10-23', 'nama_sales' => 'Andi', 'checkin_pertama_hari_ini' => '08:10'],
            ['tanggal_data' => '2026-10-23', 'nama_sales' => 'Citra', 'checkin_pertama_hari_ini' => null],
            ['tanggal_data' => '2026-10-23', 'nama_sales' => 'Dedi', 'checkin_pertama_hari_ini' => ''],
            ['tanggal_data' => '2026-10-22', 'nama_sales' => 'Eko', 'checkin_pertama_hari_ini' => '08:00'],
        ]);

        DB::table('absensi_sales_terlambat')->insert([
            ['tanggal' => '2026-10-23', 'nama_sales' => 'Andi', 'telat' => '00:10'],
            ['tanggal' => '2026-10-23', 'nama_sales' => 'Dedi', 'telat' => '00:25'],
            ['tanggal' => '2026-10-22', 'nama_sales' => 'Eko', 'telat' => '00:05'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_kpi_counts_for_today(): void
    {
        $this->get('/checkin_pagi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpi.sudah_absen', 2)
                ->where('kpi.belum_absen', 2)
                ->where('kpi.terlambat', 2)
            );
    }

    public function test_kpi_follows_selected_tanggal(): void
    {
        $this->get('/checkin_pagi?tanggal=2026-10-22')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpi.sudah_absen', 1)
                ->where('kpi.belum_absen', 0)
                ->where('kpi.terlambat', 1)
            );
    }

    public function test_kpi_counts_all_rows_not_just_current_page(): void
    {
        DB::table('checkin_pagi')->insert(
            array_map(fn ($i) => [
                'tanggal_data' => '2026-10-23',
                'nama_sales' => "Sales {$i}",
                'checkin_pertama_hari_ini' => '08:30',
            ], range(1, 60))
        );

        $this->get('/checkin_pagi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tables.0.rows.total', 64)
                ->where('kpi.sudah_absen', 62)
                ->where('kpi.belum_absen', 2)
            );
    }

    public function test_page_without_kpi_has_no_kpi_props(): void
    {
        $this->get('/provinsi')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpi', null)
            );
    }
}
