<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\LoginOtomatis;
use Tests\TestCase;

class TrackingKoordinatorTest extends TestCase
{
    use LoginOtomatis, RefreshDatabase;

    public function test_page_shows_map_and_koordinator_list(): void
    {
        DB::table('tracking_koordinator')->insert(['koordinator_id' => 7, 'nama' => 'Dian', 'depo' => 'JAKSEL']);

        $this->get('/tracking_koordinator_history_location')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tracking/History')
                ->where('slug', 'tracking_koordinator_history_location')
                ->where('label', 'Koordinator')
                ->where('idParam', 'koordinator_id')
                ->where('locationsUrl', route('tracking-koordinator.locations'))
                ->where('countsUrl', route('tracking-koordinator.counts'))
                ->has('people', 1, fn (Assert $orang) => $orang
                    ->where('id', 7)
                    ->where('nama', 'Dian')
                    ->etc())
            );
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
