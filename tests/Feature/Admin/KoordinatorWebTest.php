<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Menu web yang membaca data HP koordinator: Join Visit, Absensi Koordinator, Tracking Koordinator.
class KoordinatorWebTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['mobile.zona_waktu' => 'Asia/Jakarta']);

        DB::table('mk_karyawan')->insert(['id' => 138, 'kategori' => 'koordinator', 'nama' => 'Koor Dua', 'depo_id' => 22, 'koordinator_id' => null, 'aktif' => true]);
        DB::table('mk_area')->insert(['id' => 70, 'nama' => 'Sidoarjo Kota 01', 'depo_id' => 46]);
        DB::table('mk_outlet')->insert(['id' => 11, 'kode' => 'OUT-11', 'nama' => 'Toko Sebelas', 'area_id' => 70]);
    }

    private function joinVisit(int $id, int $koordinator, int $sales, array $lain = []): void
    {
        DB::table('trx_join_visit')->insert($lain + [
            'id' => $id, 'koordinator_id' => $koordinator, 'sales_id' => $sales, 'depo_id' => 46, 'outlet_id' => 11, 'area_id' => 70,
            'tanggal' => '2026-10-26', 'mulai_at' => '2026-10-26 03:00:00', 'selesai_at' => '2026-10-26 03:15:00',
            'durasi_detik' => 900, 'jarak_m' => 12, 'catatan' => 'Display rapi', 'foto_id' => '11111111-1111-4111-8111-111111111111',
        ]);
    }

    public function test_menu_join_visit_menampilkan_data_hp_dengan_jam_wib(): void
    {
        $this->joinVisit(1, 137, 1);
        $this->joinVisit(2, 137, 3, ['selesai_at' => null, 'durasi_detik' => null, 'foto_id' => null, 'sumber' => 'lama', 'tanggal' => '2026-10-20']);

        $this->get('/join_visit')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.same_as', null)
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('tables.0.name', 'trx_join_visit')
                    ->where('tables.0.aksi', 'foto')
                    ->where('tables.0.rows.total', 2)
                    ->where('tables.0.rows.data.0.koordinator', 'Koor Satu')
                    ->where('tables.0.rows.data.0.sales', 'Rudi Hartono')
                    ->where('tables.0.rows.data.0.outlet', 'Toko Sebelas')
                    ->where('tables.0.rows.data.0.area', 'Sidoarjo Kota 01')
                    ->where('tables.0.rows.data.0.mulai', '10:00')
                    ->where('tables.0.rows.data.0.selesai', '10:15')
                    ->where('tables.0.rows.data.0.durasi', '15 menit')
                    ->where('tables.0.rows.data.0.sumber', 'Aplikasi HP')
                    ->where('tables.0.rows.data.1.selesai', 'Berlangsung')
                    ->where('tables.0.rows.data.1.sumber', 'Aplikasi lama')
                )
            );

        // Lap. Join Visit memakai data yang sama (HP + riwayat lama hasil migrasi).
        $this->get('/lap_join_visit')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $page) => $page->where('tables.0.name', 'trx_join_visit'))
            );
    }

    public function test_koordinator_hanya_melihat_join_visit_miliknya(): void
    {
        $this->joinVisit(1, 137, 1);
        $this->joinVisit(2, 138, 2);
        $this->actingAs($this->akun('koor1', 'koordinator', 137));

        $this->get('/join_visit')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->loadDeferredProps(fn (Assert $page) => $page
                    ->where('tables.0.rows.total', 1)
                    ->where('tables.0.rows.data.0.id', 1)
                )
            );
    }

    public function test_absensi_sales_dan_koordinator_terpisah(): void
    {
        DB::table('trx_absensi')->insert([
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => now()->toDateString(), 'masuk_at' => now(), 'terlambat' => false],
            ['karyawan_id' => 137, 'depo_id' => 46, 'tanggal' => now()->toDateString(), 'masuk_at' => now(), 'terlambat' => false],
        ]);

        $this->get('/absensi_sales')->assertOk()->assertSee('Rudi Hartono')->assertDontSee('Koor Satu</td>', false);
        $this->get('/absensi_koordinator')->assertOk()->assertSee('Koor Satu')->assertDontSee('Rudi Hartono');
    }

    public function test_tracking_koordinator_menggabungkan_data_lama_dan_hp(): void
    {
        DB::table('tracking_koordinator')->insert(['koordinator_id' => 137, 'nama' => 'Koor Satu', 'depo' => 'Sidoarjo']);
        DB::table('tracking_koordinator_lokasi')->insert(['koordinator_id' => 137, 'tanggal' => '2026-10-26', 'waktu' => '2026-10-26 07:30:00', 'lat' => -6.1, 'lng' => 106.7]);
        DB::table('trx_tracking')->insert([
            ['karyawan_id' => 137, 'recorded_at' => '2026-10-26 01:00:00', 'lat' => -6.2, 'lng' => 106.8], // 08:00 WIB
            ['karyawan_id' => 138, 'recorded_at' => '2026-10-26 02:00:00', 'lat' => -6.3, 'lng' => 106.9],
            ['karyawan_id' => 1, 'recorded_at' => '2026-10-26 02:00:00', 'lat' => -6.3, 'lng' => 106.9],   // sales: tidak dihitung
            ['karyawan_id' => 137, 'recorded_at' => '2026-10-26 18:00:00', 'lat' => -6.2, 'lng' => 106.8], // 27 Okt WIB
        ]);

        // Koordinator yang hanya ada di master (pakai HP) ikut tampil.
        $this->get('/tracking_koordinator_history_location?tanggal=2026-10-26')->assertOk()->assertSee('Koor Dua');

        $this->getJson('/tracking_koordinator_history_location/counts?tanggal=2026-10-26')
            ->assertOk()->assertExactJson(['137' => 2, '138' => 1]);

        $this->getJson('/tracking_koordinator_history_location/locations?koordinator_id=137&tanggal=2026-10-26')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('locations.0.waktu', '2026-10-26 07:30:00')
            ->assertJsonPath('locations.1.waktu', '2026-10-26 08:00:00');
    }
}
