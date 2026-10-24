<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class TrackingKoordinatorTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    public function test_page_shows_map_and_koordinator_list(): void
    {
        DB::table('tracking_koordinator')->insert(['koordinator_id' => 7, 'nama' => 'Dian', 'depo' => 'JAKSEL']);

        $response = $this->get('/tracking_koordinator_history_location')
            ->assertOk()
            ->assertSee('id="tracking_map"', false)
            ->assertSee('Dian');

        $response->assertDontSee('fas fa-', false);
        $response->assertSee('<svg', false);
    }

    public function test_locations_endpoint_returns_route_in_time_order(): void
    {
        DB::table('tracking_koordinator')->insert(['koordinator_id' => 7, 'nama' => 'Dian', 'depo' => 'JAKSEL']);
        DB::table('master_depo')->insert(['nama' => 'JAKSEL', 'latitude' => -6.26, 'longitude' => 106.81]);
        DB::table('tracking_koordinator_lokasi')->insert([
            ['koordinator_id' => 7, 'tanggal' => '2026-10-21', 'waktu' => '2026-10-21 09:00:00', 'lat' => -6.2, 'lng' => 106.8],
            ['koordinator_id' => 7, 'tanggal' => '2026-10-21', 'waktu' => '2026-10-21 08:00:00', 'lat' => -6.1, 'lng' => 106.7],
            ['koordinator_id' => 7, 'tanggal' => '2026-10-22', 'waktu' => '2026-10-22 08:00:00', 'lat' => -6.3, 'lng' => 106.9],
        ]);

        $this->getJson('/tracking_koordinator_history_location/locations?koordinator_id=7&tanggal=2026-10-21')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('locations.0.waktu', '2026-10-21 08:00:00')
            ->assertJsonPath('depo.nama', 'JAKSEL');
    }

    public function test_counts_endpoint_returns_points_per_koordinator_for_a_date(): void
    {
        DB::table('tracking_koordinator_lokasi')->insert([
            ['koordinator_id' => 7, 'tanggal' => '2026-10-21', 'waktu' => '2026-10-21 08:00:00', 'lat' => -6.1, 'lng' => 106.7],
            ['koordinator_id' => 7, 'tanggal' => '2026-10-21', 'waktu' => '2026-10-21 09:00:00', 'lat' => -6.2, 'lng' => 106.8],
            ['koordinator_id' => 9, 'tanggal' => '2026-10-21', 'waktu' => '2026-10-21 09:00:00', 'lat' => -6.2, 'lng' => 106.8],
            ['koordinator_id' => 9, 'tanggal' => '2026-10-22', 'waktu' => '2026-10-22 08:00:00', 'lat' => -6.3, 'lng' => 106.9],
        ]);

        $this->getJson('/tracking_koordinator_history_location/counts?tanggal=2026-10-21')
            ->assertOk()
            ->assertExactJson(['7' => 2, '9' => 1]);
    }
}
