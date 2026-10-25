<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
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
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tracking/History')
                ->where('slug', 'tracking_sales_history_location')
                ->where('label', 'Sales')
                ->where('idParam', 'sales_id')
                ->where('groups', ['7' => 'Dian', '8' => 'Eko'])
                ->has('people', 2)
            );
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

    /** GPS dari HP sales motoris (trx_tracking, jam UTC) digabung dengan data lama dan tampil dalam WIB. */
    private function seedHp(): void
    {
        DB::table('mk_karyawan')->insert([
            ['id' => 11, 'kategori' => 'sales', 'nama' => 'Rina', 'depo_id' => null, 'koordinator_id' => 7, 'aktif' => true],
            ['id' => 21, 'kategori' => 'sales', 'nama' => 'Sari HP', 'depo_id' => null, 'koordinator_id' => 9, 'aktif' => true],
            ['id' => 9, 'kategori' => 'koordinator', 'nama' => 'Fajar', 'depo_id' => null, 'koordinator_id' => null, 'aktif' => true],
            ['id' => 22, 'kategori' => 'sales', 'nama' => 'Tono Nonaktif', 'depo_id' => null, 'koordinator_id' => null, 'aktif' => false],
        ]);
        DB::table('trx_tracking')->insert([
            ['karyawan_id' => 11, 'recorded_at' => '2026-10-21 01:30:00', 'lat' => -6.15, 'lng' => 106.75, 'akurasi_m' => 12], // 08:30 WIB
            ['karyawan_id' => 21, 'recorded_at' => '2026-10-20 23:30:00', 'lat' => -6.4, 'lng' => 107.0, 'akurasi_m' => 8],   // 06:30 WIB tgl 21
            ['karyawan_id' => 21, 'recorded_at' => '2026-10-21 18:00:00', 'lat' => -6.4, 'lng' => 107.0, 'akurasi_m' => 8],   // 01:00 WIB tgl 22
            ['karyawan_id' => 9, 'recorded_at' => '2026-10-21 02:00:00', 'lat' => -6.3, 'lng' => 106.9, 'akurasi_m' => 5],    // koordinator: tidak dihitung
        ]);
    }

    public function test_page_lists_sales_from_master_who_use_the_app(): void
    {
        $this->seedSales();
        $this->seedHp();

        $this->get('/tracking_sales_history_location?tanggal=2026-10-21')
            ->assertOk()
            ->assertSee('Sari HP')
            ->assertSee('Fajar')
            ->assertDontSee('Tono Nonaktif');
    }

    public function test_locations_merge_old_data_and_phone_gps_in_wib_order(): void
    {
        $this->seedSales();
        $this->seedHp();

        $this->getJson('/tracking_sales_history_location/locations?sales_id=11&tanggal=2026-10-21')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('locations.0.waktu', '2026-10-21 08:00:00')
            ->assertJsonPath('locations.1.waktu', '2026-10-21 08:30:00')
            ->assertJsonPath('locations.1.akurasi', 12)
            ->assertJsonPath('locations.2.waktu', '2026-10-21 09:00:00');

        $this->getJson('/tracking_sales_history_location/locations?sales_id=21&tanggal=2026-10-21')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('sales.nama', 'Sari HP')
            ->assertJsonPath('locations.0.waktu', '2026-10-21 06:30:00');
    }

    public function test_counts_include_phone_gps_of_sales_only(): void
    {
        $this->seedSales();
        $this->seedHp();

        $this->getJson('/tracking_sales_history_location/counts?tanggal=2026-10-21')
            ->assertOk()
            ->assertExactJson(['11' => 3, '21' => 1]);
    }

    public function test_default_date_follows_latest_phone_gps(): void
    {
        $this->seedSales();
        $this->seedHp();

        $this->get('/tracking_sales_history_location')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tanggal', '2026-10-22'));
    }
}
