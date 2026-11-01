<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\DB;

// Daftar kunjungan outlet di drawer Riwayat Lacak Motoris: kunci `kunjungan` pada endpoint locations (Sales).
class KunjunganDrawerRiwayatTest extends AdminTestCase
{
    private const URL = '/tracking_sales_history_location/locations';

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('mk_outlet')->insert([
            ['id' => 11, 'kode' => 'OT-11', 'nama' => 'Toko Makmur'],
            ['id' => 12, 'kode' => 'OT-12', 'nama' => 'Warung Bu Sri'],
        ]);
        DB::table('trx_outlet_baru')->insert(['id' => 5, 'karyawan_id' => 1, 'nama' => 'Kios Baru', 'lat' => -7.4, 'lng' => 112.7]);

        // Rudi (1), 24 Okt 2026. Jam disimpan UTC: 02:00 UTC = 09:00 WIB.
        $beli = DB::table('trx_kunjungan')->insertGetId(['karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 11, 'tanggal' => '2026-10-24',
            'checkin_at' => '2026-10-24 02:00:00', 'checkout_at' => '2026-10-24 02:15:00', 'durasi_detik' => 900,
            'lat' => -7.45, 'lng' => 112.71, 'jarak_m' => 12, 'di_luar_rute' => true, 'mock_location' => true]);
        DB::table('trx_penjualan')->insert([
            ['kode' => 'J-1', 'kunjungan_id' => $beli, 'karyawan_id' => 1, 'tanggal' => '2026-10-24', 'jumlah_sku' => 1, 'total_qty' => 2, 'total' => 1000, 'total_diskon' => 0, 'is_effective_call' => true],
        ]);
        // Kunjungan kedua: penjualannya dibatalkan, jadi dihitung tidak beli.
        $batal = DB::table('trx_kunjungan')->insertGetId(['karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 12, 'tanggal' => '2026-10-24',
            'checkin_at' => '2026-10-24 03:30:00', 'tidak_beli_kategori' => 'toko_tutup', 'lat' => null, 'lng' => null]);
        DB::table('trx_penjualan')->insert([
            ['kode' => 'J-2', 'kunjungan_id' => $batal, 'karyawan_id' => 1, 'tanggal' => '2026-10-24', 'jumlah_sku' => 1, 'total_qty' => 1, 'total' => 500, 'total_diskon' => 0, 'is_effective_call' => false, 'dibatalkan_at' => '2026-10-24 04:00:00'],
        ]);
        // Outlet baru, paling awal, dan kunjungan di tanggal lain / motoris lain yang tidak boleh ikut.
        DB::table('trx_kunjungan')->insert(['karyawan_id' => 1, 'depo_id' => 46, 'outlet_baru_id' => 5, 'tanggal' => '2026-10-24', 'checkin_at' => '2026-10-24 01:00:00', 'checkout_at' => '2026-10-24 01:20:00', 'lat' => -7.4, 'lng' => 112.7]);
        DB::table('trx_kunjungan')->insert(['karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 11, 'tanggal' => '2026-10-23', 'checkin_at' => '2026-10-23 02:00:00']);
        DB::table('trx_kunjungan')->insert(['karyawan_id' => 2, 'depo_id' => 22, 'outlet_id' => 11, 'tanggal' => '2026-10-24', 'checkin_at' => '2026-10-24 02:00:00']);
    }

    public function test_kunjungan_urut_jam_dengan_outlet_hasil_dan_waktu_lokal(): void
    {
        $res = $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-24')->assertOk();

        $this->assertSame(['Kios Baru', 'Toko Makmur', 'Warung Bu Sri'], array_column($res->json('kunjungan'), 'outlet'));
        $this->assertSame(['08:00', '09:00', '10:30'], array_column($res->json('kunjungan'), 'jam'));

        $res->assertJsonPath('kunjungan.1.keluar', '09:15')
            ->assertJsonPath('kunjungan.1.durasi_menit', 15)
            ->assertJsonPath('kunjungan.1.beli', true)
            ->assertJsonPath('kunjungan.1.di_luar_rute', true)
            ->assertJsonPath('kunjungan.1.lokasi_palsu', true)
            ->assertJsonPath('kunjungan.1.lat', -7.45)
            ->assertJsonPath('kunjungan.2.beli', false)
            ->assertJsonPath('kunjungan.2.keterangan', 'Toko tutup')
            ->assertJsonPath('kunjungan.2.lat', null);
    }

    public function test_perjalanan_antar_kunjungan_dari_checkout_ke_checkin_berikutnya(): void
    {
        $k = $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-24')->assertOk()->json('kunjungan');

        // Pertama: tidak ada kunjungan sebelumnya.
        $this->assertNull($k[0]['perjalanan']);
        // Kios Baru checkout 01:20 UTC, Toko Makmur checkin 02:00 UTC: 40 menit; (-7.4, 112.7) ke (-7.45, 112.71) sekitar 5,7 km.
        $this->assertSame(40, $k[1]['perjalanan']['menit']);
        $this->assertEqualsWithDelta(5670, $k[1]['perjalanan']['jarak_m'], 80);
        // Toko Makmur checkout 02:15, Warung checkin 03:30: 75 menit; Warung tanpa koordinat, jadi jarak kosong.
        $this->assertSame(75, $k[2]['perjalanan']['menit']);
        $this->assertNull($k[2]['perjalanan']['jarak_m']);
    }

    public function test_perjalanan_kosong_bila_checkout_sebelumnya_kosong_atau_waktu_tumpang_tindih(): void
    {
        // Warung (10:30 WIB) belum checkout, jadi kunjungan setelahnya tidak punya perjalanan.
        DB::table('trx_kunjungan')->insert(['karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 11, 'tanggal' => '2026-10-24',
            'checkin_at' => '2026-10-24 05:00:00', 'checkout_at' => '2026-10-24 05:10:00', 'lat' => -7.5, 'lng' => 112.8]);
        // Checkin lebih awal dari checkout kunjungan sebelumnya (05:10): data tumpang tindih.
        DB::table('trx_kunjungan')->insert(['karyawan_id' => 1, 'depo_id' => 46, 'outlet_id' => 12, 'tanggal' => '2026-10-24',
            'checkin_at' => '2026-10-24 05:05:00', 'lat' => -7.5, 'lng' => 112.8]);

        $k = $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-24')->assertOk()->json('kunjungan');

        $this->assertCount(5, $k);
        $this->assertNull($k[3]['perjalanan']);
        $this->assertNull($k[4]['perjalanan']);
    }

    public function test_alasan_tidak_beli_dirapikan_untuk_hp_dan_data_lama(): void
    {
        // Tegar (3), 24 Okt 2026: urutan mengikuti jam checkin.
        $kunjungan = fn (int $jam, array $lain) => DB::table('trx_kunjungan')->insert([
            'karyawan_id' => 3, 'depo_id' => 46, 'outlet_id' => 11, 'tanggal' => '2026-10-24', 'checkin_at' => sprintf('2026-10-24 0%d:00:00', $jam),
        ] + $lain);
        $kunjungan(1, ['tidak_beli_kategori' => 'pemilik_tidak_ada', 'tidak_beli_keterangan' => 'sedang keluar']);
        $kunjungan(2, ['tidak_beli_kategori' => 'kode_baru']);
        $kunjungan(3, ['tidak_beli_keterangan' => 'harga naik']);
        $kunjungan(4, ['keterangan_lama' => 'Stok Banyak', 'sumber' => 'lama', 'sumber_id' => 9001]);
        $kunjungan(5, []);

        $this->assertSame(
            ['Pemilik tidak di tempat — sedang keluar', 'kode_baru', 'harga naik', 'Stok Banyak', null],
            array_column($this->getJson(self::URL.'?sales_id=3&tanggal=2026-10-24')->assertOk()->json('kunjungan'), 'keterangan')
        );
    }

    public function test_ringkasan_penjualan_per_kunjungan(): void
    {
        $k = $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-24')->assertOk()->json('kunjungan');

        // Toko Makmur: satu transaksi aktif (J-1).
        $this->assertSame(['transaksi' => 1, 'total' => 1000, 'sku' => 1, 'qty' => 2], $k[1]['penjualan']);
        $this->assertTrue($k[1]['beli']);
        // Warung Bu Sri: satu-satunya penjualan dibatalkan, jadi tidak ada penjualan aktif.
        $this->assertNull($k[2]['penjualan']);
        $this->assertFalse($k[2]['beli']);
        // Kios Baru: tanpa penjualan.
        $this->assertNull($k[0]['penjualan']);
    }

    public function test_beberapa_transaksi_pada_satu_kunjungan_dijumlahkan_tanpa_yang_dibatalkan(): void
    {
        $id = DB::table('trx_kunjungan')->where('karyawan_id', 1)->where('checkin_at', '2026-10-24 02:00:00')->value('id');
        DB::table('trx_penjualan')->insert(['kode' => 'J-3', 'kunjungan_id' => $id, 'karyawan_id' => 1, 'tanggal' => '2026-10-24', 'jumlah_sku' => 2, 'total_qty' => 3.5, 'total' => 2500, 'total_diskon' => 0, 'is_effective_call' => true]);
        DB::table('trx_penjualan')->insert(['kode' => 'J-4', 'kunjungan_id' => $id, 'karyawan_id' => 1, 'tanggal' => '2026-10-24', 'jumlah_sku' => 9, 'total_qty' => 9, 'total' => 99999, 'total_diskon' => 0, 'is_effective_call' => true, 'dibatalkan_at' => '2026-10-24 05:00:00']);

        $k = $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-24')->assertOk()->json('kunjungan');

        $this->assertSame(['transaksi' => 2, 'total' => 3500, 'sku' => 3, 'qty' => 5.5], $k[1]['penjualan']);
    }

    public function test_kode_outlet_dari_master_dan_null_untuk_outlet_baru(): void
    {
        $k = $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-24')->assertOk()->json('kunjungan');

        // Urutan jam: Kios Baru (outlet baru, belum punya kode), Toko Makmur (OT-11), Warung Bu Sri (OT-12).
        $this->assertSame([null, 'OT-11', 'OT-12'], array_column($k, 'kode_outlet'));
    }

    public function test_outlet_ditandai_dari_tanda_outlet_aktif(): void
    {
        DB::table('tanda_outlet')->insert([
            'outlet_id' => 11, 'kategori' => 'outlet_fiktif', 'dibuat_oleh' => $this->admin->id, 'dibuat_nama' => 'admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Tanda outlet 12 sudah dicabut: tidak dihitung ditandai.
        DB::table('tanda_outlet')->insert([
            'outlet_id' => 12, 'kategori' => 'outlet_fiktif', 'dibuat_oleh' => $this->admin->id, 'dibuat_nama' => 'admin',
            'dicabut_at' => now(), 'dicabut_oleh' => $this->admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $k = $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-24')->assertOk()->json('kunjungan');

        // Urutan jam: Kios Baru (outlet baru, tidak ditandai), Toko Makmur (OT-11, ditandai), Warung Bu Sri (OT-12, tanda dicabut).
        $this->assertSame([false, true, false], array_column($k, 'outlet_ditandai'));
    }

    public function test_tanggal_lain_tanpa_kunjungan(): void
    {
        $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-25')->assertOk()->assertJsonPath('kunjungan', []);
    }

    public function test_kunjungan_motoris_di_luar_cakupan_role_tidak_dikirim(): void
    {
        // Koordinator Koor Satu (137) hanya mencakup bawahannya: Rudi (1) ya, Sinta (2) tidak.
        $role = $this->role('Pemantau Downline', 'downline', ['tracking_sales_history_location' => ['lihat']]);
        $this->actingAs($this->akun('pemantau', 'koordinator', 137, ['role_id' => $role]));

        $this->getJson(self::URL.'?sales_id=1&tanggal=2026-10-24')->assertOk()->assertJsonCount(3, 'kunjungan');
        $this->getJson(self::URL.'?sales_id=2&tanggal=2026-10-24')->assertOk()->assertJsonPath('kunjungan', []);
    }

    public function test_endpoint_koordinator_tanpa_kunci_kunjungan(): void
    {
        $this->getJson('/tracking_koordinator_history_location/locations?koordinator_id=137&tanggal=2026-10-24')
            ->assertOk()->assertJsonMissingPath('kunjungan');
    }
}
