<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;

// Indikasi GPS tidak wajar di respons locations Sales (drawer Riwayat Lacak Motoris): per tanggal, tanpa data lintas tanggal.
class AnomaliGpsDrawerTest extends AdminTestCase
{
    private const URL = '/tracking_sales_history_location/locations';

    /**
     * Titik data lama (tracking_sales_lokasi) satu menit sekali; $lintang dalam kelipatan 0,05 derajat (sekitar 5,6 km) dari -6,60.
     *
     * @param  array<int, int>  $lintang
     */
    private function titikLama(int $sales, string $tanggal, array $lintang): void
    {
        foreach ($lintang as $i => $k) {
            DB::table('tracking_sales_lokasi')->insert([
                'sales_id' => $sales, 'tanggal' => $tanggal, 'waktu' => sprintf('%s 08:%02d:00', $tanggal, $i),
                'lat' => -6.60 + $k * 0.05, 'lng' => 106.80,
            ]);
        }
    }

    private function anomali(string $tanggal, int $sales = 1)
    {
        return $this->getJson(self::URL."?sales_id={$sales}&tanggal={$tanggal}")->assertOk();
    }

    public function test_pola_lompatan_berulang_pada_data_lama_menjadi_indikasi(): void
    {
        $this->titikLama(1, '2026-10-24', [0, 1, 0, 1, 0, 2]);

        $res = $this->anomali('2026-10-24');

        $res->assertJsonPath('anomali_gps.tingkat', 'indikasi')
            ->assertJsonPath('anomali_gps.lompatan', 5)
            ->assertJsonPath('anomali_gps.kembali_ke_asal', 3)
            ->assertJsonPath('anomali_gps.titik_mock', 0)
            ->assertJsonPath('anomali_gps.terjauh.jam', '08:04')
            ->assertJsonCount(5, 'anomali_gps.segmen')
            ->assertJsonPath('anomali_gps.segmen.0.dari.waktu', '2026-10-24 08:00:00')
            ->assertJsonPath('anomali_gps.segmen.0.kembali', true);
    }

    /** Titik data lama berjeda 30 menit mulai 08:00, jadi kecepatannya tidak pernah mendekati batas lompatan mustahil. */
    private function titikPelan(int $sales, string $tanggal, array $lintang): void
    {
        foreach ($lintang as $i => $k) {
            DB::table('tracking_sales_lokasi')->insert([
                'sales_id' => $sales, 'tanggal' => $tanggal, 'waktu' => date('Y-m-d H:i:s', strtotime("{$tanggal} 08:00:00") + $i * 1800),
                'lat' => -6.60 + $k * 0.05, 'lng' => 106.80,
            ]);
        }
    }

    public function test_tiga_titik_menyimpang_tunggal_menjadi_indikasi_tanpa_lompatan_mustahil(): void
    {
        $this->titikPelan(1, '2026-10-24', [0, 0, 1, 0, 0, 2, 0, 0, 3, 0, 0]);

        $this->anomali('2026-10-24')
            ->assertJsonPath('anomali_gps.tingkat', 'indikasi')
            ->assertJsonPath('anomali_gps.lompatan', 0)
            ->assertJsonPath('anomali_gps.menyimpang', 3)
            ->assertJsonPath('anomali_gps.menyimpang_terjauh.jam', '12:00')
            ->assertJsonCount(3, 'anomali_gps.titik_menyimpang')
            ->assertJsonPath('anomali_gps.titik_menyimpang.0.waktu', '2026-10-24 09:00:00')
            ->assertJsonPath('anomali_gps.segmen', []);
    }

    public function test_titik_menyimpang_di_hari_lain_tidak_digabung(): void
    {
        // Masing-masing hari hanya 2 titik menyimpang (di bawah ambang 3); digabung jadi 4.
        $this->titikPelan(1, '2026-10-25', [0, 0, 1, 0, 0, 1, 0, 0]);
        $this->titikPelan(1, '2026-10-26', [0, 0, 1, 0, 0, 1, 0, 0]);

        $this->anomali('2026-10-25')->assertJsonPath('anomali_gps', null);
        $this->anomali('2026-10-26')->assertJsonPath('anomali_gps', null);
    }

    public function test_rute_normal_tidak_punya_anomali(): void
    {
        foreach (range(0, 5) as $i) {
            DB::table('tracking_sales_lokasi')->insert([
                'sales_id' => 1, 'tanggal' => '2026-10-29', 'waktu' => sprintf('2026-10-29 08:%02d:00', $i * 3),
                'lat' => -6.60 + $i * 0.0045, 'lng' => 106.80,
            ]);
        }

        $this->anomali('2026-10-29')->assertJsonPath('total', 6)->assertJsonPath('anomali_gps', null);
    }

    public function test_hanya_tanggal_yang_diminta_yang_dianalisis_bukan_gabungan_antar_tanggal(): void
    {
        // Masing-masing hari punya 3 lompatan (2 kembali): di bawah ambang. Digabung jadi 6 dan 4, yang akan melewatinya.
        $this->titikLama(1, '2026-10-25', [0, 1, 0, 1]);
        $this->titikLama(1, '2026-10-26', [0, 1, 0, 1]);

        $this->anomali('2026-10-25')->assertJsonPath('anomali_gps', null);
        $this->anomali('2026-10-26')->assertJsonPath('anomali_gps', null);
    }

    public function test_pola_di_hari_lain_tidak_membuat_hari_yang_bersih_tampak_curiga(): void
    {
        $this->titikLama(1, '2026-10-24', [0, 1, 0, 1, 0, 2]);
        $this->titikLama(1, '2026-10-30', [0, 0, 0]);

        $this->anomali('2026-10-30')->assertJsonPath('anomali_gps', null);
    }

    public function test_titik_mock_dari_hp_langsung_terdeteksi(): void
    {
        // recorded_at disimpan UTC: 05:00 UTC = 12:00 WIB, tanggal 27 Okt.
        DB::table('trx_tracking')->insert([
            ['karyawan_id' => 1, 'recorded_at' => '2026-10-27 05:00:00', 'lat' => -6.60, 'lng' => 106.80, 'mock_location' => true],
            ['karyawan_id' => 1, 'recorded_at' => '2026-10-27 05:03:00', 'lat' => -6.60, 'lng' => 106.80, 'mock_location' => false],
        ]);

        $this->anomali('2026-10-27')
            ->assertJsonPath('anomali_gps.tingkat', 'terdeteksi')
            ->assertJsonPath('anomali_gps.titik_mock', 1)
            ->assertJsonPath('anomali_gps.lompatan', 0)
            ->assertJsonPath('anomali_gps.terjauh', null)
            ->assertJsonPath('anomali_gps.segmen', []);
    }

    public function test_kunjungan_lokasi_palsu_dari_hp_terdeteksi_walau_tanpa_titik_gps(): void
    {
        DB::table('mk_outlet')->insert(['id' => 11, 'kode' => 'OT-11', 'nama' => 'Toko Makmur']);
        DB::table('trx_kunjungan')->insert([
            'karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 11, 'tanggal' => '2026-10-28',
            'checkin_at' => '2026-10-28 02:00:00', 'lat' => -7.45, 'lng' => 112.71, 'mock_location' => true,
        ]);

        $this->anomali('2026-10-28')
            ->assertJsonPath('total', 0)
            ->assertJsonPath('anomali_gps.tingkat', 'terdeteksi')
            ->assertJsonPath('anomali_gps.kunjungan_mock', 1)
            ->assertJsonPath('anomali_gps.titik_mock', 0);
    }

    public function test_tanggal_tanpa_data_tidak_punya_anomali(): void
    {
        $this->anomali('2026-10-31')->assertJsonPath('total', 0)->assertJsonPath('anomali_gps', null);
    }

    public function test_endpoint_koordinator_tidak_memuat_anomali_gps(): void
    {
        $this->getJson('/tracking_koordinator_history_location/locations?koordinator_id=137&tanggal=2026-10-24')
            ->assertOk()->assertJsonMissingPath('anomali_gps');
    }
}
