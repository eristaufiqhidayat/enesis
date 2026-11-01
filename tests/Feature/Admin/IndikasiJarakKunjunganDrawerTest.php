<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;

// Indikasi kunjungan outlet berturutan berjarak dekat (spec 054) di respons locations Sales (drawer Riwayat Lacak Motoris).
class IndikasiJarakKunjunganDrawerTest extends AdminTestCase
{
    private const URL = '/tracking_sales_history_location/locations';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('mk_outlet')->insert([
            ['id' => 11, 'kode' => 'OT-11', 'nama' => 'Toko A'],
            ['id' => 12, 'kode' => 'OT-12', 'nama' => 'Toko B'],
            ['id' => 13, 'kode' => 'OT-13', 'nama' => 'Toko C'],
            ['id' => 14, 'kode' => 'OT-14', 'nama' => 'Toko D'],
        ]);
    }

    /** 4 kunjungan berturutan di koordinat yang sama (replikasi kasus Ade Suherman): outlet berbeda, jarak 0 m. */
    private function kunjunganBerdekatan(string $tanggal): void
    {
        $outlet = [11, 12, 13, 14];
        foreach ($outlet as $i => $o) {
            DB::table('trx_kunjungan')->insert([
                'karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => $o, 'tanggal' => $tanggal,
                'checkin_at' => sprintf('%s 0%d:00:00', $tanggal, $i + 1), 'checkout_at' => sprintf('%s 0%d:02:00', $tanggal, $i + 1),
                'lat' => -6.5731, 'lng' => 106.7759,
            ]);
        }
    }

    public function test_kunjungan_berturutan_di_koordinat_sama_menjadi_indikasi(): void
    {
        $this->kunjunganBerdekatan('2026-10-24');

        $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-24')->assertOk()
            ->assertJsonPath('indikasi_jarak_kunjungan.jumlah', 4)
            ->assertJsonPath('indikasi_jarak_kunjungan.outlet_id', [11, 12, 13, 14])
            ->assertJsonPath('indikasi_jarak_kunjungan.saran.kategori', 'pola_kunjungan_tidak_wajar');
    }

    public function test_kunjungan_normal_tidak_punya_indikasi(): void
    {
        DB::table('trx_kunjungan')->insert([
            ['karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 11, 'tanggal' => '2026-10-25', 'checkin_at' => '2026-10-25 01:00:00', 'checkout_at' => '2026-10-25 01:10:00', 'lat' => -7.45, 'lng' => 112.71],
            ['karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 12, 'tanggal' => '2026-10-25', 'checkin_at' => '2026-10-25 02:00:00', 'checkout_at' => '2026-10-25 02:10:00', 'lat' => -7.50, 'lng' => 112.80],
        ]);

        $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-25')->assertOk()->assertJsonPath('indikasi_jarak_kunjungan', null);
    }

    public function test_ambang_bisa_diubah_dari_pengaturan(): void
    {
        DB::table('pengaturan_admin')->insert(['kunci' => 'deteksi_kunjungan.min_kunjungan_berturutan', 'nilai' => '5', 'created_at' => now(), 'updated_at' => now()]);
        $this->kunjunganBerdekatan('2026-10-26'); // hanya 4 berturutan, sekarang di bawah ambang 5

        $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-26')->assertOk()->assertJsonPath('indikasi_jarak_kunjungan', null);
    }

    public function test_tanggal_tanpa_kunjungan_tidak_punya_indikasi(): void
    {
        $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-27')->assertOk()->assertJsonPath('indikasi_jarak_kunjungan', null);
    }

    public function test_endpoint_koordinator_tidak_memuat_indikasi_jarak_kunjungan(): void
    {
        $this->getJson('/tracking_koordinator_history_location/locations?koordinator_id=137&tanggal=2026-10-24')
            ->assertOk()->assertJsonMissingPath('indikasi_jarak_kunjungan');
    }
}
