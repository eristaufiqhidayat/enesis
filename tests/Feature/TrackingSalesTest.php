<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class TrackingSalesTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    private function seedSales(): void
    {
        DB::table('tracking_sales')->insert([
            ['sales_id' => 11, 'nama' => 'Rina', 'koordinator_id' => 7, 'koordinator_nama' => 'Dian', 'depo' => 'JAKSEL', 'depo_lat' => -6.26, 'depo_lng' => 106.81],
            ['sales_id' => 12, 'nama' => 'Budi', 'koordinator_id' => 8, 'koordinator_nama' => 'Eko', 'depo' => 'Bekasi', 'depo_lat' => null, 'depo_lng' => null],
        ]);
        DB::table('tracking_sales_lokasi')->insert([
            ['sales_id' => 11, 'tanggal' => '2026-10-21', 'waktu' => '2026-10-21 09:00:00', 'lat' => -6.2, 'lng' => 106.8],
            ['sales_id' => 11, 'tanggal' => '2026-10-21', 'waktu' => '2026-10-21 08:00:00', 'lat' => -6.1, 'lng' => 106.7],
            ['sales_id' => 12, 'tanggal' => '2026-10-22', 'waktu' => '2026-10-22 08:00:00', 'lat' => -6.3, 'lng' => 106.9],
        ]);
    }

    public function test_page_shows_map_sales_list_and_koordinator_filter(): void
    {
        $this->seedSales();

        $this->get('/tracking_sales_history_location')
            ->assertOk()
            ->assertSee('id="tracking_map"', false)
            ->assertSee('id="group_filter"', false)
            ->assertSee('Rina')
            ->assertSee('Eko');
    }

    public function test_locations_endpoint_returns_route_in_time_order_with_depo(): void
    {
        $this->seedSales();

        $this->getJson('/tracking_sales_history_location/locations?sales_id=11&tanggal=2026-10-21')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('locations.0.waktu', '2026-10-21 08:00:00')
            ->assertJsonPath('depo.nama', 'JAKSEL');
    }

    public function test_counts_endpoint_returns_points_per_sales_for_a_date(): void
    {
        $this->seedSales();

        $this->getJson('/tracking_sales_history_location/counts?tanggal=2026-10-21')
            ->assertOk()
            ->assertExactJson(['11' => 2]);
    }
}
