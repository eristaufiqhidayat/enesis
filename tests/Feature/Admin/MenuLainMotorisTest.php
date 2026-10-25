<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// Tracking last seen, Lap. Join Visit, Lap. Absensi Koordinator, Sesi Login, Lap. Insentif Sales dari data motoris.
class MenuLainMotorisTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 10, 7)->setTime(12, 0));
    }

    public function test_tracking_last_seen_sales_dan_koordinator(): void
    {
        DB::table('trx_tracking')->insert([
            ['karyawan_id' => 1, 'recorded_at' => '2026-10-07 03:00:00', 'lat' => -7.1, 'lng' => 112.1, 'baterai' => 80],
            ['karyawan_id' => 1, 'recorded_at' => '2026-10-07 04:00:00', 'lat' => -7.2, 'lng' => 112.2, 'baterai' => 75],
            ['karyawan_id' => 137, 'recorded_at' => '2026-10-06 01:00:00', 'lat' => -7.3, 'lng' => 112.3, 'baterai' => null],
        ]);
        // Tegar belum pakai HP: posisi terakhir dari aplikasi lama (jam WIB).
        DB::table('tracking_sales_lokasi')->insert(['sales_id' => 3, 'tanggal' => '2026-10-05', 'waktu' => '2026-10-05 15:00:00', 'lat' => -7.4, 'lng' => 112.4]);

        $this->get('/tracking_sales')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.name', 'tracking_last_seen_sales')
                ->where('tables.0.rows.total', 3) // sales aktif: Rudi, Sinta, Tegar
                ->where('tables.0.rows.data.0.nama', 'Rudi Hartono')
                ->where('tables.0.rows.data.0.terakhir_terlihat', '2026-10-07 11:00:00')
                ->where('tables.0.rows.data.0.latitude', -7.2)
                ->where('tables.0.rows.data.0.baterai', '75%')
                ->where('tables.0.rows.data.0.sumber', 'Aplikasi HP')
                ->where('tables.0.rows.data.1.nama', 'Sinta Wulandari')
                ->where('tables.0.rows.data.1.terakhir_terlihat', 'Belum ada data')
                ->where('tables.0.rows.data.2.nama', 'Tegar Mahendra')
                ->where('tables.0.rows.data.2.terakhir_terlihat', '2026-10-05 15:00:00')
                ->where('tables.0.rows.data.2.sumber', 'Aplikasi lama')
            ));

        $this->get('/tracking_koordinator')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.terakhir_terlihat', '2026-10-06 08:00:00')
            ));
    }

    public function test_lap_join_visit_dan_absensi_koordinator(): void
    {
        DB::table('trx_join_visit')->insert(['koordinator_id' => 137, 'sales_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-06', 'mulai_at' => '2026-10-06 02:00:00']);
        DB::table('trx_absensi')->insert([
            ['karyawan_id' => 137, 'depo_id' => 46, 'tanggal' => '2026-10-06', 'masuk_at' => '2026-10-06 00:30:00'],
            ['karyawan_id' => 1, 'depo_id' => 46, 'tanggal' => '2026-10-06', 'masuk_at' => '2026-10-06 00:40:00'],
        ]);

        $this->get('/lap_join_visit')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.name', 'trx_join_visit')
                ->where('tables.0.rows.data.0.koordinator', 'Koor Satu')
            ));

        $this->get('/lap_absensi_koordinator?dari=2026-10-06&sampai=2026-10-06')->assertOk()
            ->assertSee('Koor Satu')->assertDontSee('Rudi Hartono');
    }

    public function test_sesi_login_hp_aktif(): void
    {
        $sales = $this->akun('rudi', 'sales', 1);
        DB::table('mobile_tokens')->insert([
            ['user_id' => $sales->id, 'token_hash' => str_repeat('a', 64), 'device_id' => 'HP-123', 'device_model' => 'Redmi 12', 'app_version' => '1.1.0',
                'last_used_at' => '2026-10-07 03:00:00', 'revoked_at' => null, 'created_at' => '2026-10-07 01:00:00'],
            ['user_id' => $sales->id, 'token_hash' => str_repeat('b', 64), 'device_id' => 'HP-LAMA', 'device_model' => null, 'app_version' => null,
                'last_used_at' => null, 'revoked_at' => '2026-10-06 00:00:00', 'created_at' => '2026-10-05 01:00:00'],
        ]);

        $this->get('/login_session')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.total', 1)
                ->where('tables.0.rows.data.0.username_nickname', 'rudi')
                ->where('tables.0.rows.data.0.nama_karyawan', 'Rudi Hartono')
                ->where('tables.0.rows.data.0.nama_koordinator', 'Koor Satu')
                ->where('tables.0.rows.data.0.device_token', 'Redmi 12 · HP-123 · v1.1.0')
                ->where('tables.0.rows.data.0.terakhir_aktif', '2026-10-07 10:00:00')
            ));
    }

    public function test_lap_insentif_sales_per_bulan(): void
    {
        DB::table('rumus_insentif_sales')->insert([
            ['tot_ec_lower_bound' => '0', 'tot_ec_upper_bound' => '1', 'total_insentif' => '0'],
            ['tot_ec_lower_bound' => '2', 'tot_ec_upper_bound' => '100', 'total_insentif' => '50000'],
        ]);
        DB::table('sales_point_conversion_per_warehouse')->insert(['depo' => 'Sidoarjo', 'poin_insentif_rp' => 100]);
        $kj = DB::table('trx_kunjungan')->insertGetId(['karyawan_id' => 1, 'tanggal' => '2026-10-06', 'checkin_at' => '2026-10-06 02:00:00']);
        $jual = fn ($kode, $tgl, $ec, $poin) => ['kode' => $kode, 'kunjungan_id' => $kj, 'karyawan_id' => 1, 'tanggal' => $tgl,
            'jumlah_sku' => 1, 'total_qty' => 1, 'total' => 1000, 'is_effective_call' => $ec, 'poin' => $poin];
        DB::table('trx_penjualan')->insert([$jual('A', '2026-10-01', true, 10), $jual('B', '2026-10-06', true, 5), $jual('C', '2026-10-06', false, 0), $jual('D', '2026-09-30', true, 99)]);

        $this->get('/lap_insentif_sales?tanggal=2026-10-15')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->loadDeferredProps(fn (Assert $p) => $p
                ->where('tables.0.rows.data.0.nama_sales', 'Rudi Hartono')
                ->where('tables.0.rows.data.0.periode', 'Oktober 2026')
                ->where('tables.0.rows.data.0.no_payroll', 'INS-202610-1')
                ->where('tables.0.rows.data.0.total_ec', 2)
                ->where('tables.0.rows.data.0.insentif_ec', 'Rp 50.000')
                ->where('tables.0.rows.data.0.insentif_poin', 'Rp 1.500')
                ->where('tables.0.rows.data.0.total_insentif', 'Rp 51.500')
                ->where('tables.0.kpi.3.value', 'Rp 50.000')
            ));
    }
}
